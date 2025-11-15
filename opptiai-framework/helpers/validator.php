<?php
/**
 * OpptiAI Framework - Validation Helpers
 *
 * @package OpptiAI\Framework\Helpers
 */

namespace OpptiAI\Framework\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Validator {

	/**
	 * Validate email address
	 *
	 * @param string $email Email to validate
	 * @return bool True if valid
	 */
	public static function email( $email ) {
		return is_email( $email ) !== false;
	}

	/**
	 * Validate URL
	 *
	 * @param string $url URL to validate
	 * @return bool True if valid
	 */
	public static function url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Validate integer
	 *
	 * @param mixed $value Value to validate
	 * @return bool True if valid
	 */
	public static function int( $value ) {
		return filter_var( $value, FILTER_VALIDATE_INT ) !== false;
	}

	/**
	 * Validate float
	 *
	 * @param mixed $value Value to validate
	 * @return bool True if valid
	 */
	public static function float( $value ) {
		return filter_var( $value, FILTER_VALIDATE_FLOAT ) !== false;
	}

	/**
	 * Validate boolean
	 *
	 * @param mixed $value Value to validate
	 * @return bool True if valid
	 */
	public static function bool( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) !== null;
	}

	/**
	 * Validate JSON string
	 *
	 * @param string $json JSON to validate
	 * @return bool True if valid
	 */
	public static function json( $json ) {
		if ( ! is_string( $json ) ) {
			return false;
		}
		json_decode( $json );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Validate nonce
	 *
	 * @param string $nonce Nonce to validate
	 * @param string $action Action name
	 * @return bool True if valid
	 */
	public static function nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Validate required field
	 *
	 * @param mixed $value Value to validate
	 * @return bool True if not empty
	 */
	public static function required( $value ) {
		if ( is_string( $value ) ) {
			return trim( $value ) !== '';
		}
		return ! empty( $value );
	}

	/**
	 * Validate minimum length
	 *
	 * @param string $value Value to validate
	 * @param int    $min   Minimum length
	 * @return bool True if meets minimum
	 */
	public static function min_length( $value, $min ) {
		return strlen( $value ) >= $min;
	}

	/**
	 * Validate maximum length
	 *
	 * @param string $value Value to validate
	 * @param int    $max   Maximum length
	 * @return bool True if within maximum
	 */
	public static function max_length( $value, $max ) {
		return strlen( $value ) <= $max;
	}

	/**
	 * Validate against regex pattern
	 *
	 * @param string $value   Value to validate
	 * @param string $pattern Regex pattern
	 * @return bool True if matches
	 */
	public static function pattern( $value, $pattern ) {
		return preg_match( $pattern, $value ) === 1;
	}

	/**
	 * Validate in array
	 *
	 * @param mixed $value Value to validate
	 * @param array $array Array of valid values
	 * @return bool True if in array
	 */
	public static function in_array( $value, $array ) {
		return in_array( $value, $array, true );
	}
}
