<?php
/**
 * Settings Trait
 *
 * Provides standardized settings management.
 *
 * @package Optti\Framework\Traits
 */

namespace Optti\Framework\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Settings
 *
 * Use this trait to add settings management to classes.
 */
trait Settings {

	/**
	 * Get settings option key.
	 *
	 * @return string Option key.
	 */
	abstract protected function get_option_key();

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	protected function get_default_settings() {
		return [];
	}

	/**
	 * Get all settings.
	 *
	 * @return array Settings array.
	 */
	public function get_settings() {
		$defaults = $this->get_default_settings();
		$settings = get_option( $this->get_option_key(), [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get a specific setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed Setting value or default.
	 */
	public function get_setting( $key, $default = null ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update a setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public function update_setting( $key, $value ) {
		$settings         = $this->get_settings();
		$settings[ $key ] = $value;

		return update_option( $this->get_option_key(), $settings );
	}

	/**
	 * Update multiple settings.
	 *
	 * @param array $settings Settings to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_settings( $settings ) {
		$current = $this->get_settings();
		$updated = wp_parse_args( $settings, $current );

		return update_option( $this->get_option_key(), $updated );
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key.
	 * @return bool True on success, false on failure.
	 */
	public function delete_setting( $key ) {
		$settings = $this->get_settings();
		unset( $settings[ $key ] );

		return update_option( $this->get_option_key(), $settings );
	}
}

