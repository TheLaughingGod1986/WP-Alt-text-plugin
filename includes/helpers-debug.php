<?php
/**
 * Debug helper functions for BeepBeep AI.
 *
 * @package BeepBeep_AI
 * @since 4.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bbai_debug_enabled' ) ) {
	/**
	 * Determine whether plugin debug logging is enabled.
	 *
	 * @return bool
	 */
	function bbai_debug_enabled() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}

if ( ! function_exists( 'bbai_debug_is_sensitive_key' ) ) {
	/**
	 * Check whether a context key should be redacted.
	 *
	 * @param string $key Context key.
	 * @return bool
	 */
	function bbai_debug_is_sensitive_key( $key ) {
		$normalized = strtolower( (string) $key );
		$sensitive  = array(
			'token',
			'api_key',
			'apikey',
			'authorization',
			'password',
			'secret',
			'license_key',
			'jwt',
		);

		foreach ( $sensitive as $needle ) {
			if ( strpos( $normalized, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'bbai_debug_redact_context' ) ) {
	/**
	 * Recursively redact sensitive values from debug context.
	 *
	 * @param mixed       $value Context value.
	 * @param string|null $key   Optional context key.
	 * @return mixed
	 */
	function bbai_debug_redact_context( $value, $key = null ) {
		if ( is_string( $key ) && bbai_debug_is_sensitive_key( $key ) ) {
			return '[REDACTED]';
		}

		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $child_key => $child_value ) {
				$sanitized[ $child_key ] = bbai_debug_redact_context( $child_value, (string) $child_key );
			}
			return $sanitized;
		}

		if ( is_object( $value ) ) {
			$sanitized = array();
			foreach ( get_object_vars( $value ) as $child_key => $child_value ) {
				$sanitized[ $child_key ] = bbai_debug_redact_context( $child_value, (string) $child_key );
			}
			return $sanitized;
		}

		return $value;
	}
}

if ( ! function_exists( 'bbai_debug_log' ) ) {
	/**
	 * Write a redacted, single-line debug message when debug logging is enabled.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	function bbai_debug_log( $message, $context = array() ) {
		if ( ! bbai_debug_enabled() ) {
			return;
		}

		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$line = '[BBAI] ' . sanitize_text_field( (string) $message );

		if ( ! is_array( $context ) ) {
			$context = array( 'value' => $context );
		}

		if ( ! empty( $context ) ) {
			$redacted = bbai_debug_redact_context( $context );
			$json     = wp_json_encode(
				$redacted,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);

			if ( is_string( $json ) && $json !== '' ) {
				$line .= ' ' . str_replace( array( "\r", "\n" ), ' ', $json );
			}
		}

		call_user_func( 'error_log', $line );
	}
}
