<?php
/**
 * OpptiAI Framework - Sanitization Helpers
 *
 * @package OpptiAI\Framework\Helpers
 */

namespace OpptiAI\Framework\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitizer {

	/**
	 * Sanitize text input
	 *
	 * @param string $input Raw input
	 * @return string Sanitized text
	 */
	public static function text( $input ) {
		return sanitize_text_field( $input );
	}

	/**
	 * Sanitize textarea input
	 *
	 * @param string $input Raw input
	 * @return string Sanitized textarea content
	 */
	public static function textarea( $input ) {
		return sanitize_textarea_field( $input );
	}

	/**
	 * Sanitize email address
	 *
	 * @param string $email Raw email
	 * @return string Sanitized email
	 */
	public static function email( $email ) {
		return sanitize_email( $email );
	}

	/**
	 * Sanitize URL
	 *
	 * @param string $url Raw URL
	 * @return string Sanitized URL
	 */
	public static function url( $url ) {
		return esc_url_raw( $url );
	}

	/**
	 * Sanitize integer
	 *
	 * @param mixed $value Raw value
	 * @return int Sanitized integer
	 */
	public static function int( $value ) {
		return intval( $value );
	}

	/**
	 * Sanitize float
	 *
	 * @param mixed $value Raw value
	 * @return float Sanitized float
	 */
	public static function float( $value ) {
		return floatval( $value );
	}

	/**
	 * Sanitize boolean
	 *
	 * @param mixed $value Raw value
	 * @return bool Sanitized boolean
	 */
	public static function bool( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize array of text fields
	 *
	 * @param array $array Raw array
	 * @return array Sanitized array
	 */
	public static function array_text( $array ) {
		if ( ! is_array( $array ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $array );
	}

	/**
	 * Sanitize key (alphanumeric with underscores and dashes)
	 *
	 * @param string $key Raw key
	 * @return string Sanitized key
	 */
	public static function key( $key ) {
		return sanitize_key( $key );
	}

	/**
	 * Sanitize HTML class
	 *
	 * @param string $class Raw class name
	 * @return string Sanitized class name
	 */
	public static function html_class( $class ) {
		return sanitize_html_class( $class );
	}

	/**
	 * Sanitize JSON input
	 *
	 * @param string $json Raw JSON
	 * @return array|null Decoded array or null on failure
	 */
	public static function json( $json ) {
		$decoded = json_decode( $json, true );
		return ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : null;
	}

	/**
	 * Sanitize slug
	 *
	 * @param string $slug Raw slug
	 * @return string Sanitized slug
	 */
	public static function slug( $slug ) {
		return sanitize_title( $slug );
	}

	/**
	 * Sanitize file name
	 *
	 * @param string $filename Raw filename
	 * @return string Sanitized filename
	 */
	public static function filename( $filename ) {
		return sanitize_file_name( $filename );
	}
}
