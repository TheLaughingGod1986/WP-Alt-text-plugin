<?php
/**
 * First-touch marketing attribution persistence for telemetry and checkout.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures and serves UTM/referrer attribution for the site install.
 */
class BBAI_Attribution {

	public const OPTION_KEY = 'bbai_marketing_attribution';

	/**
	 * Keys stored in the attribution payload.
	 *
	 * @return string[]
	 */
	public static function attribution_keys(): array {
		return array(
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_content',
			'utm_term',
			'referrer',
			'landing_page',
			'acquisition_channel',
			'captured_at',
		);
	}

	/**
	 * Persist first-touch attribution when available.
	 *
	 * @param array<string,mixed> $payload Attribution payload from browser.
	 */
	public static function maybe_capture( array $payload = array() ): void {
		$existing = get_option( self::OPTION_KEY, array() );
		if ( is_array( $existing ) && ! empty( $existing['captured_at'] ) ) {
			return;
		}

		$sanitized = self::sanitize_payload( $payload );
		if ( empty( $sanitized ) ) {
			return;
		}

		$sanitized['captured_at'] = gmdate( 'c' );
		if ( empty( $sanitized['acquisition_channel'] ) ) {
			$sanitized['acquisition_channel'] = self::infer_channel( $sanitized );
		}

		update_option( self::OPTION_KEY, $sanitized, false );
	}

	/**
	 * Return stored attribution for checkout / telemetry bridges.
	 *
	 * @return array<string,string>
	 */
	public static function get_payload(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? self::sanitize_payload( $stored ) : array();
	}

	/**
	 * Checkout context keys the backend needs to start and label a checkout.
	 *
	 * @return string[]
	 */
	public static function checkout_context_keys(): array {
		return array( 'trigger_feature', 'trigger_location', 'source_page', 'target_plan', 'current_plan', 'source' );
	}

	/**
	 * Attribution keys a checkout request may carry.
	 *
	 * @return string[]
	 */
	public static function checkout_attribution_keys(): array {
		return array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer', 'landing_page' );
	}

	/**
	 * Reduce checkout attribution to the allowed keys: the checkout context,
	 * the UTM tags, the referrer host only and the landing page path only.
	 * Anything else (ids, keys, email, channel, timestamps) is dropped.
	 *
	 * @param array<string,mixed> $payload Stored attribution merged with the click context.
	 * @return array<string,string>
	 */
	public static function for_checkout( array $payload ): array {
		$out = array();
		foreach ( array_merge( self::checkout_context_keys(), self::checkout_attribution_keys() ) as $key ) {
			if ( ! isset( $payload[ $key ] ) || ! is_scalar( $payload[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $payload[ $key ] );
			if ( 'referrer' === $key ) {
				$value = self::referrer_host( $value );
			} elseif ( 'landing_page' === $key ) {
				$value = self::landing_path( $value );
			}
			if ( '' === $value ) {
				continue;
			}
			$out[ $key ] = mb_substr( $value, 0, 500 );
		}
		return $out;
	}

	/**
	 * Host part of a referrer, lower case, without scheme, user, port, path or query.
	 *
	 * @param string $value Referrer URL or host.
	 */
	public static function referrer_host( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $value ) ) {
			$value = 'http://' . ltrim( $value, '/' );
		}
		$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( $value, PHP_URL_HOST ) : parse_url( $value, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Path of a landing page, without scheme, host, query or fragment.
	 *
	 * @param string $value Landing page path or URL.
	 */
	public static function landing_path( string $value ): string {
		$parts = preg_split( '/[?#]/', trim( $value ), 2 );
		$value = is_array( $parts ) ? (string) $parts[0] : '';
		if ( preg_match( '#^([a-z][a-z0-9+.\-]*:)?//#i', $value ) ) {
			$path  = function_exists( 'wp_parse_url' ) ? wp_parse_url( $value, PHP_URL_PATH ) : parse_url( $value, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			$value = is_string( $path ) ? $path : '';
		}
		return $value;
	}

	/**
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,string>
	 */
	public static function sanitize_payload( array $payload ): array {
		$out = array();
		foreach ( self::attribution_keys() as $key ) {
			if ( ! isset( $payload[ $key ] ) || ! is_scalar( $payload[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $payload[ $key ] );
			if ( '' === $value ) {
				continue;
			}
			$out[ $key ] = mb_substr( $value, 0, 500 );
		}
		return $out;
	}

	/**
	 * @param array<string,string> $payload Sanitized attribution.
	 */
	private static function infer_channel( array $payload ): string {
		if ( ! empty( $payload['utm_source'] ) ) {
			return sanitize_key( $payload['utm_source'] );
		}
		if ( ! empty( $payload['referrer'] ) ) {
			return 'referral';
		}
		return 'direct';
	}
}
