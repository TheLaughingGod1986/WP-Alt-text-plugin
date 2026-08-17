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
