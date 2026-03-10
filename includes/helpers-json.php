<?php
/**
 * JSON Helper Functions
 *
 * Provides safe JSON decoding with proper validation and sanitization.
 *
 * @package BeepBeep_AI
 * @since 4.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bbai_json_decode_array' ) ) {
	/**
	 * Safely decode a JSON string to an array with validation.
	 *
	 * @param string $json_string   The JSON string to decode.
	 * @param array  $sanitize_map  Optional. Map of keys to sanitization callbacks.
	 *                              Use 'absint', 'sanitize_text_field', 'sanitize_key',
	 *                              'esc_url_raw', 'wp_kses_post', or a callable.
	 *                              Keys not in map are sanitized with sanitize_text_field by default.
	 * @param bool   $sanitize_all  Optional. Whether to sanitize all values. Default true.
	 * @return array|null Returns sanitized array on success, null on failure.
	 */
	function bbai_json_decode_array( $json_string, array $sanitize_map = array(), $sanitize_all = true ) {
		if ( ! is_string( $json_string ) || $json_string === '' ) {
			return null;
		}

		$decoded = json_decode( $json_string, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			return null;
		}

		if ( ! $sanitize_all && empty( $sanitize_map ) ) {
			return $decoded;
		}

		return bbai_sanitize_array_recursive( $decoded, $sanitize_map, $sanitize_all );
	}
}

if ( ! function_exists( 'bbai_sanitize_array_recursive' ) ) {
	/**
	 * Recursively sanitize an array's values.
	 *
	 * @param array $array         The array to sanitize.
	 * @param array $sanitize_map  Map of keys to sanitization callbacks.
	 * @param bool  $sanitize_all  Whether to sanitize unmapped keys.
	 * @return array The sanitized array.
	 */
	function bbai_sanitize_array_recursive( array $array, array $sanitize_map = array(), $sanitize_all = true ) {
		$sanitized = array();

		foreach ( $array as $key => $value ) {
			// Sanitize the key itself.
			$clean_key = is_string( $key ) ? sanitize_key( $key ) : absint( $key );

			if ( is_array( $value ) ) {
				// Recurse into nested arrays.
				$sanitized[ $clean_key ] = bbai_sanitize_array_recursive( $value, $sanitize_map, $sanitize_all );
			} elseif ( isset( $sanitize_map[ $key ] ) ) {
				// Use specific sanitization callback from map.
				$sanitized[ $clean_key ] = bbai_apply_sanitizer( $value, $sanitize_map[ $key ] );
			} elseif ( $sanitize_all ) {
				// Apply default sanitization based on value type.
				$sanitized[ $clean_key ] = bbai_sanitize_value_by_type( $value );
			} else {
				$sanitized[ $clean_key ] = $value;
			}
		}

		return $sanitized;
	}
}

if ( ! function_exists( 'bbai_apply_sanitizer' ) ) {
	/**
	 * Apply a sanitization callback to a value.
	 *
	 * @param mixed           $value     The value to sanitize.
	 * @param string|callable $sanitizer The sanitizer name or callback.
	 * @return mixed The sanitized value.
	 */
	function bbai_apply_sanitizer( $value, $sanitizer ) {
		if ( is_callable( $sanitizer ) ) {
			return call_user_func( $sanitizer, $value );
		}

		switch ( $sanitizer ) {
			case 'absint':
				return absint( $value );

			case 'intval':
				return intval( $value );

			case 'floatval':
				return floatval( $value );

			case 'sanitize_text_field':
				return is_string( $value ) ? sanitize_text_field( $value ) : '';

			case 'sanitize_key':
				return is_string( $value ) ? sanitize_key( $value ) : '';

			case 'sanitize_email':
				return is_string( $value ) ? sanitize_email( $value ) : '';

			case 'esc_url_raw':
				return is_string( $value ) ? esc_url_raw( $value ) : '';

			case 'wp_kses_post':
				return is_string( $value ) ? wp_kses_post( $value ) : '';

			case 'bool':
			case 'boolean':
				return (bool) $value;

			case 'raw':
			case 'none':
				return $value;

			default:
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}
	}
}

if ( ! function_exists( 'bbai_sanitize_value_by_type' ) ) {
	/**
	 * Sanitize a value based on its PHP type.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed The sanitized value.
	 */
	function bbai_sanitize_value_by_type( $value ) {
		if ( is_null( $value ) ) {
			return null;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) ) {
			return intval( $value );
		}

		if ( is_float( $value ) ) {
			return floatval( $value );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		// Arrays should not reach here due to recursion, but handle just in case.
		if ( is_array( $value ) ) {
			return bbai_sanitize_array_recursive( $value );
		}

		return $value;
	}
}
