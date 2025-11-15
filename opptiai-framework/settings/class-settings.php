<?php
/**
 * OpptiAI Framework - Settings Manager
 *
 * Handles plugin settings storage and retrieval
 *
 * @package OpptiAI\Framework\Settings
 */

namespace OpptiAI\Framework\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OpptiAI\Framework\Helpers\Sanitizer;

class Settings {

	/**
	 * Option key prefix
	 *
	 * @var string
	 */
	protected $prefix = 'opptiai_';

	/**
	 * Settings cache
	 *
	 * @var array
	 */
	protected $cache = array();

	/**
	 * Constructor
	 *
	 * @param string $prefix Optional prefix for option keys
	 */
	public function __construct( $prefix = 'opptiai_' ) {
		$this->prefix = $prefix;
	}

	/**
	 * Get setting value
	 *
	 * @param string $key     Setting key
	 * @param mixed  $default Default value
	 * @return mixed Setting value
	 */
	public function get( $key, $default = null ) {
		$option_key = $this->prefix . $key;

		// Check cache first
		if ( isset( $this->cache[ $option_key ] ) ) {
			return $this->cache[ $option_key ];
		}

		$value = get_option( $option_key, $default );

		// Cache the value
		$this->cache[ $option_key ] = $value;

		return $value;
	}

	/**
	 * Set setting value
	 *
	 * @param string $key      Setting key
	 * @param mixed  $value    Setting value
	 * @param bool   $autoload Whether to autoload
	 * @return bool True if successful
	 */
	public function set( $key, $value, $autoload = false ) {
		$option_key = $this->prefix . $key;

		$result = update_option( $option_key, $value, $autoload );

		// Update cache
		if ( $result ) {
			$this->cache[ $option_key ] = $value;
		}

		return $result;
	}

	/**
	 * Delete setting
	 *
	 * @param string $key Setting key
	 * @return bool True if successful
	 */
	public function delete( $key ) {
		$option_key = $this->prefix . $key;

		// Remove from cache
		unset( $this->cache[ $option_key ] );

		return delete_option( $option_key );
	}

	/**
	 * Check if setting exists
	 *
	 * @param string $key Setting key
	 * @return bool True if exists
	 */
	public function has( $key ) {
		$option_key = $this->prefix . $key;
		return false !== get_option( $option_key, false );
	}

	/**
	 * Get multiple settings at once
	 *
	 * @param array $keys Array of setting keys
	 * @return array Associative array of settings
	 */
	public function get_multiple( $keys ) {
		$settings = array();

		foreach ( $keys as $key ) {
			$settings[ $key ] = $this->get( $key );
		}

		return $settings;
	}

	/**
	 * Set multiple settings at once
	 *
	 * @param array $settings Associative array of settings
	 * @param bool  $autoload Whether to autoload
	 * @return bool True if all successful
	 */
	public function set_multiple( $settings, $autoload = false ) {
		$success = true;

		foreach ( $settings as $key => $value ) {
			if ( ! $this->set( $key, $value, $autoload ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get all settings with prefix
	 *
	 * @return array All settings
	 */
	public function get_all() {
		global $wpdb;

		$pattern = $wpdb->esc_like( $this->prefix ) . '%';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			),
			ARRAY_A
		);

		$settings = array();

		if ( $results ) {
			foreach ( $results as $row ) {
				$key               = str_replace( $this->prefix, '', $row['option_name'] );
				$settings[ $key ] = maybe_unserialize( $row['option_value'] );
			}
		}

		return $settings;
	}

	/**
	 * Delete all settings with prefix
	 *
	 * @return bool True if successful
	 */
	public function delete_all() {
		global $wpdb;

		$pattern = $wpdb->esc_like( $this->prefix ) . '%';

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		// Clear cache
		$this->cache = array();

		return false !== $result;
	}

	/**
	 * Clear settings cache
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->cache = array();
	}

	/**
	 * Register settings with WordPress Settings API
	 *
	 * @param string $option_group  Option group name
	 * @param string $option_name   Option name
	 * @param array  $args          Optional arguments
	 * @return void
	 */
	public function register( $option_group, $option_name, $args = array() ) {
		$defaults = array(
			'type'              => 'string',
			'description'       => '',
			'sanitize_callback' => null,
			'show_in_rest'      => false,
			'default'           => null,
		);

		$args = wp_parse_args( $args, $defaults );

		register_setting( $option_group, $this->prefix . $option_name, $args );
	}

	/**
	 * Add settings section
	 *
	 * @param string   $id       Section ID
	 * @param string   $title    Section title
	 * @param callable $callback Callback function
	 * @param string   $page     Page slug
	 * @return void
	 */
	public function add_section( $id, $title, $callback, $page ) {
		add_settings_section( $id, $title, $callback, $page );
	}

	/**
	 * Add settings field
	 *
	 * @param string   $id       Field ID
	 * @param string   $title    Field title
	 * @param callable $callback Callback function
	 * @param string   $page     Page slug
	 * @param string   $section  Section ID
	 * @param array    $args     Optional arguments
	 * @return void
	 */
	public function add_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
		add_settings_field( $id, $title, $callback, $page, $section, $args );
	}

	/**
	 * Sanitize settings array
	 *
	 * @param array $settings Settings array
	 * @param array $schema   Schema definition (key => type)
	 * @return array Sanitized settings
	 */
	public function sanitize( $settings, $schema ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $schema as $key => $type ) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			$value = $settings[ $key ];

			switch ( $type ) {
				case 'text':
				case 'string':
					$sanitized[ $key ] = Sanitizer::text( $value );
					break;

				case 'textarea':
					$sanitized[ $key ] = Sanitizer::textarea( $value );
					break;

				case 'email':
					$sanitized[ $key ] = Sanitizer::email( $value );
					break;

				case 'url':
					$sanitized[ $key ] = Sanitizer::url( $value );
					break;

				case 'int':
				case 'integer':
					$sanitized[ $key ] = Sanitizer::int( $value );
					break;

				case 'float':
				case 'number':
					$sanitized[ $key ] = Sanitizer::float( $value );
					break;

				case 'bool':
				case 'boolean':
					$sanitized[ $key ] = Sanitizer::bool( $value );
					break;

				case 'array':
					$sanitized[ $key ] = is_array( $value ) ? $value : array();
					break;

				case 'json':
					$sanitized[ $key ] = Sanitizer::json( $value );
					break;

				default:
					$sanitized[ $key ] = $value;
					break;
			}
		}

		return $sanitized;
	}
}
