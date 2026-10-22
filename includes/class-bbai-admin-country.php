<?php
/**
 * Resolve the WP admin's country for upgrade checkout currency.
 *
 * Checkout calls reach the OpptiAI backend server-to-server from the WordPress
 * host, so backend edge geo sees the hosting server's country (e.g. a Polish
 * site on a US host looks "US"). The plugin therefore resolves the admin's own
 * country here and forwards it (X-Admin-Country / admin_country).
 *
 * Order, first match wins:
 *  1. Edge country header on the admin's own browser request (Cloudflare,
 *     CloudFront, App Engine, Vercel) - the admin's IP geo.
 *  2. WordPress site timezone (Settings > General), e.g. Europe/Warsaw -> PL.
 *  3. Admin user locale region, e.g. pl_PL -> PL (en_US is ignored: it is the
 *     WordPress default, not a country signal).
 * Unknown -> '' (backend and plugin both fall back to GBP).
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBAI_Admin_Country {

	/** @var array{country:string,source:string}|null */
	private static $cache = null;

	private const EDGE_HEADER_KEYS = array(
		'HTTP_CF_IPCOUNTRY',
		'CF-IPCountry',
		'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
		'HTTP_X_COUNTRY_CODE',
		'HTTP_X_APPENGINE_COUNTRY',
		'HTTP_X_VERCEL_IP_COUNTRY',
	);

	/**
	 * @return array{country:string,source:string}
	 */
	public static function resolve(): array {
		if ( is_array( self::$cache ) ) {
			return self::$cache;
		}

		$result = self::from_edge_headers();
		if ( '' === $result['country'] ) {
			$result = self::from_timezone();
		}
		if ( '' === $result['country'] ) {
			$result = self::from_locale();
		}

		/**
		 * Filter resolved request country code for upgrade checkout currency.
		 * Kept for back-compat with the 4.6.134 filter signature.
		 *
		 * @param string $country ISO 3166-1 alpha-2 code or ''.
		 * @param string $source  Where it came from (header key, 'timezone', 'locale' or '').
		 */
		$filtered = apply_filters( 'bbai_request_country_code', $result['country'], $result['source'] );
		$filtered = is_string( $filtered ) ? strtoupper( trim( $filtered ) ) : $result['country'];
		if ( ! self::is_valid_code( $filtered ) ) {
			$filtered = '';
		}

		self::$cache = array(
			'country' => $filtered,
			'source'  => '' === $filtered ? '' : $result['source'],
		);
		return self::$cache;
	}

	public static function country(): string {
		$resolved = self::resolve();
		return $resolved['country'];
	}

	/** Test helper. */
	public static function reset_cache(): void {
		self::$cache = null;
	}

	private static function is_valid_code( string $code ): bool {
		return (bool) preg_match( '/^[A-Z]{2}$/', $code ) && ! in_array( $code, array( 'XX', 'T1', 'ZZ', 'EU', 'AP' ), true );
	}

	/** @return array{country:string,source:string} */
	private static function from_edge_headers(): array {
		$server = isset( $_SERVER ) && is_array( $_SERVER ) ? $_SERVER : array();
		foreach ( self::EDGE_HEADER_KEYS as $key ) {
			if ( empty( $server[ $key ] ) || ! is_string( $server[ $key ] ) ) {
				continue;
			}
			$raw = strtoupper( trim( sanitize_text_field( wp_unslash( $server[ $key ] ) ) ) );
			if ( self::is_valid_code( $raw ) ) {
				return array( 'country' => $raw, 'source' => $key );
			}
		}
		return array( 'country' => '', 'source' => '' );
	}

	/** @return array{country:string,source:string} */
	private static function from_timezone(): array {
		$tz = function_exists( 'get_option' ) ? (string) get_option( 'timezone_string', '' ) : '';
		if ( '' === $tz || 0 === strpos( $tz, 'UTC' ) || 0 === strpos( $tz, 'Etc/' ) ) {
			return array( 'country' => '', 'source' => '' );
		}
		try {
			$location = ( new \DateTimeZone( $tz ) )->getLocation();
			$code     = is_array( $location ) && isset( $location['country_code'] ) ? strtoupper( (string) $location['country_code'] ) : '';
			if ( self::is_valid_code( $code ) ) {
				return array( 'country' => $code, 'source' => 'timezone' );
			}
		} catch ( \Exception $e ) {
			// Invalid timezone string: ignore.
		}
		return array( 'country' => '', 'source' => '' );
	}

	/** @return array{country:string,source:string} */
	private static function from_locale(): array {
		$locale = function_exists( 'get_user_locale' ) ? (string) get_user_locale() : ( function_exists( 'get_locale' ) ? (string) get_locale() : '' );
		if ( '' === $locale || 'en_US' === $locale ) {
			return array( 'country' => '', 'source' => '' );
		}
		if ( preg_match( '/^[a-z]{2,3}_([A-Z]{2})(?:_|$)/', $locale, $m ) && self::is_valid_code( $m[1] ) ) {
			return array( 'country' => $m[1], 'source' => 'locale' );
		}
		return array( 'country' => '', 'source' => '' );
	}
}
