<?php
/**
 * Product telemetry — Phase 12.
 *
 * Emits normalized events for activation, engagement, conversion, and errors.
 * Privacy: no ALT text, filenames, emails, or tokens in payloads.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central telemetry emitter + optional local ring buffer for debugging / export.
 */
class BBAI_Telemetry {

	public const RING_OPTION = 'bbai_telemetry_ring';

	public const RING_MAX = 300;

	private const POSTHOG_API_KEY = 'phc_6L7JzpjYRC8Gk4Br3YevTmjZnJsJPvoy9GK7RFdo72s';

	private const POSTHOG_API_HOST = 'https://us.i.posthog.com';

	/**
	 * Whole days between the previous stored admin visit and this request, captured when touch_last_active() runs.
	 *
	 * @var int|null
	 */
	private static $inactive_days_at_session_start = null;

	/**
	 * Emit a telemetry event (always fires `bbai_telemetry_event`; optionally persists to ring buffer).
	 *
	 * @param string $event_name snake_case name.
	 * @param array  $properties Additional scalar metadata (sanitized).
	 */
	public static function emit( string $event_name, array $properties = [] ): void {
		if ( ! apply_filters( 'bbai_telemetry_enabled', true ) ) {
			return;
		}

		$event_name = sanitize_key( $event_name );
		if ( ! preg_match( '/^[a-z0-9_]{1,80}$/', $event_name ) ) {
			return;
		}

		$properties = self::sanitize_properties( $properties );

		$envelope = [
			'event'           => $event_name,
			'timestamp'       => gmdate( 'c' ),
			'timestamp_ms'    => (int) round( microtime( true ) * 1000 ),
			'user_id'         => get_current_user_id(),
			'page'            => self::resolve_page_slug(),
			'plan_type'       => self::resolve_plan_type(),
			'plugin_version'  => defined( 'BEEPBEEP_AI_VERSION' ) ? (string) BEEPBEEP_AI_VERSION : '',
			'site_id'         => self::resolve_site_id(),
			'properties'      => $properties,
		];

		/**
		 * Fired for each telemetry event. Integrate PostHog, Segment, GA4, or custom sinks here.
		 *
		 * @param array $envelope Normalized payload.
		 */
		do_action( 'bbai_telemetry_event', $envelope );

		if ( apply_filters( 'bbai_telemetry_persist_ring', true ) ) {
			self::push_ring( $envelope );
		}
	}

	/**
	 * Increment per-session image processing counter (user meta + day bucket).
	 *
	 * @param int $count Success count to add.
	 */
	public static function bump_session_images_processed( int $count ): void {
		$count = max( 0, $count );
		if ( $count === 0 ) {
			return;
		}
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		$day = gmdate( 'Ymd' );
		$key = '_bbai_telemetry_session_images_' . $day;
		$cur = (int) get_user_meta( $uid, $key, true );
		update_user_meta( $uid, $key, $cur + $count );
	}

	/**
	 * Mark last admin visit for retention metrics.
	 */
	public static function touch_last_active(): void {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		$last = (int) get_user_meta( $uid, '_bbai_telemetry_last_active', true );
		self::$inactive_days_at_session_start = $last > 0
			? (int) floor( ( time() - $last ) / DAY_IN_SECONDS )
			: 0;
		update_user_meta( $uid, '_bbai_telemetry_last_active', time() );
	}

	/**
	 * Inactive whole days as of this admin page load (before last_active was updated). Used by Phase 14 retention + JS context.
	 */
	public static function inactive_days_at_session_start(): int {
		if ( null !== self::$inactive_days_at_session_start ) {
			return (int) self::$inactive_days_at_session_start;
		}
		return self::days_since_last_active();
	}

	/**
	 * Shared PostHog project API key used by the browser and server-side value events.
	 */
	public static function get_posthog_api_key(): string {
		return (string) apply_filters( 'bbai_posthog_api_key', self::POSTHOG_API_KEY );
	}

	/**
	 * Shared PostHog host for client and server-side event capture.
	 */
	public static function get_posthog_api_host(): string {
		$host = (string) apply_filters( 'bbai_posthog_api_host', self::POSTHOG_API_HOST );
		return '' !== $host ? untrailingslashit( $host ) : '';
	}

	/**
	 * Non-blocking server-side PostHog capture for trustworthy value events.
	 *
	 * @param string $event_name  Event name.
	 * @param string $distinct_id Stable PostHog distinct id.
	 * @param array  $properties  Event properties.
	 */
	public static function capture_posthog_event( string $event_name, string $distinct_id, array $properties = [] ): void {
		$event_name  = sanitize_key( $event_name );
		$distinct_id = sanitize_text_field( $distinct_id );
		if ( '' === $event_name || '' === $distinct_id ) {
			return;
		}

		if ( ! apply_filters( 'bbai_posthog_server_capture_enabled', true, $event_name, $distinct_id, $properties ) ) {
			return;
		}

		$api_key = self::get_posthog_api_key();
		$api_host = self::get_posthog_api_host();
		if ( '' === $api_key || '' === $api_host ) {
			return;
		}

		$payload = wp_json_encode(
			[
				'api_key'     => $api_key,
				'event'       => $event_name,
				'distinct_id' => $distinct_id,
				'properties'  => self::sanitize_properties( $properties ),
			]
		);

		if ( ! is_string( $payload ) || '' === $payload ) {
			return;
		}

		wp_remote_post(
			$api_host . '/capture/',
			[
				'timeout'     => 1,
				'blocking'    => false,
				'headers'     => [
					'Content-Type' => 'application/json',
				],
				'body'        => $payload,
				'data_format' => 'body',
			]
		);
	}

	/**
	 * Days since last plugin admin activity (0 = same day / first time).
	 */
	public static function days_since_last_active(): int {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return 0;
		}
		$last = (int) get_user_meta( $uid, '_bbai_telemetry_last_active', true );
		if ( $last <= 0 ) {
			return 0;
		}
		$diff = time() - $last;
		return (int) floor( $diff / DAY_IN_SECONDS );
	}

	/**
	 * @param array<string,mixed> $props Input properties.
	 * @return array<string,scalar|array>
	 */
	private static function sanitize_properties( array $props ): array {
		$out = [];
		foreach ( $props as $k => $v ) {
			$key = is_string( $k ) ? sanitize_key( $k ) : '';
			if ( '' === $key || strlen( $key ) > 48 ) {
				continue;
			}
			if ( is_bool( $v ) ) {
				$out[ $key ] = $v;
				continue;
			}
			if ( is_int( $v ) || is_float( $v ) ) {
				$out[ $key ] = $v;
				continue;
			}
			if ( is_string( $v ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( $v ), 0, 500 );
				continue;
			}
			if ( is_array( $v ) ) {
				$nested = [];
				$i      = 0;
				foreach ( $v as $nk => $nv ) {
					if ( $i >= 12 ) {
						break;
					}
					if ( ! is_string( $nk ) ) {
						continue;
					}
					$snk = sanitize_key( $nk );
					if ( '' === $snk ) {
						continue;
					}
					if ( is_scalar( $nv ) ) {
						$nested[ $snk ] = is_string( $nv )
							? mb_substr( sanitize_text_field( (string) $nv ), 0, 200 )
							: $nv;
						++$i;
					}
				}
				if ( ! empty( $nested ) ) {
					$out[ $key ] = $nested;
				}
			}
		}
		return $out;
	}

	private static function resolve_site_id(): string {
		if ( ! function_exists( __NAMESPACE__ . '\\get_site_identifier' ) ) {
			$file = BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
		if ( function_exists( __NAMESPACE__ . '\\get_site_identifier' ) ) {
			return (string) get_site_identifier();
		}
		return '';
	}

	private static function resolve_plan_type(): string {
		if ( ! class_exists( Usage_Tracker::class ) ) {
			return 'unknown';
		}
		$stats = Usage_Tracker::get_stats_display();
		$plan  = isset( $stats['plan'] ) ? sanitize_key( (string) $stats['plan'] ) : 'free';
		if ( in_array( $plan, [ 'pro', 'growth', 'agency', 'enterprise' ], true ) ) {
			return 'pro';
		}
		if ( 'free' === $plan ) {
			return 'free';
		}
		return 'unknown';
	}

	private static function resolve_page_slug(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$map  = [
			'bbai'               => 'dashboard',
			'bbai-library'       => 'alt_library',
			'bbai-analytics'     => 'analytics',
			'bbai-credit-usage'  => 'usage',
			'bbai-settings'      => 'settings',
			'bbai-debug'         => 'settings',
			'bbai-guide'         => 'help',
			'bbai-onboarding'    => 'onboarding',
			'bbai-ui-kit'        => 'ui_kit',
			'bbai-agency-overview' => 'agency_overview',
		];
		if ( isset( $map[ $page ] ) ) {
			return $map[ $page ];
		}
		return '' !== $page ? 'other' : 'unknown';
	}

	/**
	 * @param array<string,mixed> $envelope Envelope to store.
	 */
	private static function push_ring( array $envelope ): void {
		$ring = get_option( self::RING_OPTION, [] );
		if ( ! is_array( $ring ) ) {
			$ring = [];
		}
		$ring[] = $envelope;
		if ( count( $ring ) > self::RING_MAX ) {
			$ring = array_slice( $ring, -1 * self::RING_MAX );
		}
		update_option( self::RING_OPTION, $ring, false );
	}
}

/**
 * Global helper for legacy / trait call sites.
 *
 * @param string               $event_name Event name (snake_case).
 * @param array<string,mixed> $properties  Optional properties.
 */
function bbai_telemetry_emit( string $event_name, array $properties = [] ): void {
	BBAI_Telemetry::emit( $event_name, $properties );
}
