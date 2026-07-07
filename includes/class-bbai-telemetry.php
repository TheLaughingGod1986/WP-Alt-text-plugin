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

	public const TELEMETRY_SCHEMA_VERSION = '1';

	public const RING_OPTION = 'bbai_telemetry_ring';

	public const RING_MAX = 300;

	public const CONSENT_OPTION = 'bbai_telemetry_consent';

	public const LIFECYCLE_QUEUE_OPTION = 'bbai_telemetry_lifecycle_queue';

	public const INSTALLED_MARKER_OPTION = 'bbai_telemetry_plugin_installed';

	public const LEGACY_INSTALLED_MARKER_OPTION = 'bbai_telemetry_plugin_installed_logged';

	public const ACTIVATION_MARKER_OPTION = 'bbai_telemetry_plugin_activated';

	public const CURRENT_VERSION_OPTION = 'bbai_telemetry_current_plugin_version';

	public const PREVIOUS_VERSION_OPTION = 'bbai_telemetry_previous_plugin_version';

	public const LEGACY_VERSION_OPTION = 'bbai_telemetry_plugin_version';

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
	public static function emit( string $event_name, array $properties = array() ): void {
		if ( ! apply_filters( 'bbai_telemetry_enabled', true ) ) {
			return;
		}

		$event_name = sanitize_key( $event_name );
		$event_name = self::normalize_event_name( $event_name, $properties );
		if ( ! preg_match( '/^[a-z0-9_]{1,80}$/', $event_name ) ) {
			return;
		}

		$site_install_id = self::resolve_site_id();
		$properties      = self::with_site_identity( self::sanitize_properties( $properties ), $site_install_id );

		$envelope = array(
			'event'           => $event_name,
			'timestamp'       => gmdate( 'c' ),
			'timestamp_ms'    => (int) round( microtime( true ) * 1000 ),
			'user_id'         => get_current_user_id(),
			'page'            => self::resolve_page_slug(),
			'plan_type'       => self::resolve_plan_type(),
			'plugin_version'  => defined( 'BEEPBEEP_AI_VERSION' ) ? (string) BEEPBEEP_AI_VERSION : '',
			'site_id'         => $site_install_id,
			'site_install_id' => $site_install_id,
			'properties'      => $properties,
		);

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
		if ( 0 === $count ) {
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
		$last                                 = (int) get_user_meta( $uid, '_bbai_telemetry_last_active', true );
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
	 * Whether this site has opted in to external product analytics.
	 */
	public static function has_telemetry_consent(): bool {
		if ( defined( 'BBAI_TELEMETRY_CONSENT' ) ) {
			return (bool) BBAI_TELEMETRY_CONSENT;
		}

		$stored = get_option( self::CONSENT_OPTION, false );
		if ( false === $stored ) {
			$granted = true;
		} else {
			$granted = in_array( $stored, array( 'yes', '1', 1, true ), true );
		}

		/**
		 * Override external telemetry consent for managed environments.
		 *
		 * @param bool $granted Whether the site has opted in.
		 */
		return (bool) apply_filters( 'bbai_telemetry_consent_granted', $granted );
	}

	/**
	 * Shared PostHog project API key used by the browser and server-side value events.
	 */
	public static function get_posthog_api_key(): string {
		return (string) apply_filters( 'bbai_posthog_api_key', self::POSTHOG_API_KEY );
	}

	/**
	 * WordPress.org plugin directory slug used in telemetry payloads.
	 */
	public static function get_plugin_slug(): string {
		if ( defined( 'BEEPBEEP_AI_PLUGIN_BASENAME' ) ) {
			$basename = (string) BEEPBEEP_AI_PLUGIN_BASENAME;
			$slug     = dirname( $basename );
			if ( '.' !== $slug && '' !== $slug ) {
				return sanitize_key( $slug );
			}
			$slug = basename( $basename, '.php' );
			if ( '' !== $slug ) {
				return sanitize_key( $slug );
			}
		}
		return 'beepbeep-ai-alt-text-generator';
	}

	/**
	 * Enrich sanitized properties with the canonical telemetry contract (for tests and bridges).
	 *
	 * @param array<string,mixed> $properties Event properties.
	 * @return array<string,mixed>
	 */
	public static function enrich_properties( array $properties = array() ): array {
		$site_install_id = self::resolve_site_id();
		return self::with_site_identity( self::sanitize_properties( $properties ), $site_install_id );
	}

	/**
	 * Shared PostHog host for client and server-side event capture.
	 */
	public static function get_posthog_api_host(): string {
		$host = (string) apply_filters( 'bbai_posthog_api_host', self::POSTHOG_API_HOST );
		return '' !== $host ? untrailingslashit( $host ) : '';
	}

	/**
	 * Normalize dashboard-facing plan slugs.
	 *
	 * @param mixed $plan Plan value from usage, account, or license state.
	 */
	public static function normalize_plan_value( $plan ): string {
		$plan = is_scalar( $plan ) ? sanitize_key( (string) $plan ) : '';
		if ( in_array( $plan, array( 'free', 'trial', 'pro', 'agency' ), true ) ) {
			return $plan;
		}
		if ( 'anonymous_trial' === $plan ) {
			return 'trial';
		}
		if ( in_array( $plan, array( 'starter', 'growth', 'enterprise' ), true ) ) {
			return 'pro';
		}
		return 'unknown';
	}

	/**
	 * Capture activation/update/install semantics from the real WordPress lifecycle hook.
	 */
	public static function record_activation_lifecycle(): void {
		if ( false === get_option( self::CONSENT_OPTION, false ) ) {
			update_option( self::CONSENT_OPTION, 'yes', false );
		}

		if ( ! get_option( self::INSTALLED_MARKER_OPTION, false ) && ! get_option( self::LEGACY_INSTALLED_MARKER_OPTION, false ) ) {
			self::queue_lifecycle_event( 'plugin_installed' );
			update_option( self::INSTALLED_MARKER_OPTION, gmdate( 'c' ), false );
			update_option( self::LEGACY_INSTALLED_MARKER_OPTION, '1', false );
		}

		$current_version  = defined( 'BEEPBEEP_AI_VERSION' ) ? (string) BEEPBEEP_AI_VERSION : '';
		$previous_version = (string) get_option( self::CURRENT_VERSION_OPTION, '' );
		if ( '' === $previous_version ) {
			$previous_version = (string) get_option( self::LEGACY_VERSION_OPTION, '' );
		}

		if ( '' !== $current_version && '' !== $previous_version && $previous_version !== $current_version ) {
			self::queue_lifecycle_event(
				'plugin_updated',
				array(
					'previous_version'        => $previous_version,
					'previous_plugin_version' => $previous_version,
				)
			);
		}

		if ( ! get_option( self::ACTIVATION_MARKER_OPTION, false ) ) {
			self::queue_lifecycle_event( 'plugin_activated' );
			update_option( self::ACTIVATION_MARKER_OPTION, gmdate( 'c' ), false );
		}

		update_option( self::PREVIOUS_VERSION_OPTION, $previous_version, false );
		if ( '' !== $current_version ) {
			update_option( self::CURRENT_VERSION_OPTION, $current_version, false );
			update_option( self::LEGACY_VERSION_OPTION, $current_version, false );
		}
	}

	/**
	 * Detect active-plugin updates that do not pass through register_activation_hook().
	 */
	public static function maybe_record_plugin_update(): void {
		$current_version = defined( 'BEEPBEEP_AI_VERSION' ) ? (string) BEEPBEEP_AI_VERSION : '';
		if ( '' === $current_version ) {
			return;
		}

		$stored_version = (string) get_option( self::CURRENT_VERSION_OPTION, '' );
		if ( '' === $stored_version ) {
			$stored_version = (string) get_option( self::LEGACY_VERSION_OPTION, '' );
		}

		if ( '' === $stored_version ) {
			update_option( self::CURRENT_VERSION_OPTION, $current_version, false );
			update_option( self::LEGACY_VERSION_OPTION, $current_version, false );
			return;
		}

		if ( $stored_version === $current_version ) {
			return;
		}

		update_option( self::PREVIOUS_VERSION_OPTION, $stored_version, false );
		update_option( self::CURRENT_VERSION_OPTION, $current_version, false );
		update_option( self::LEGACY_VERSION_OPTION, $current_version, false );

		self::queue_lifecycle_event(
			'plugin_updated',
			array(
				'previous_version'        => $stored_version,
				'previous_plugin_version' => $stored_version,
			)
		);
	}

	/**
	 * Queue lifecycle telemetry so activation does not depend on admin JavaScript.
	 *
	 * @param string $event_name Lifecycle event name.
	 * @param array  $properties Event properties.
	 */
	public static function queue_lifecycle_event( string $event_name, array $properties = array() ): void {
		$event_name = sanitize_key( $event_name );
		if ( ! in_array( $event_name, array( 'plugin_installed', 'plugin_activated', 'plugin_updated', 'plugin_deactivated' ), true ) ) {
			return;
		}

		$site_install_id = self::resolve_site_id();
		$properties      = self::with_site_identity(
			self::sanitize_properties(
				array_merge(
					array(
						'lifecycle_event' => $event_name,
					),
					$properties
				)
			),
			$site_install_id
		);
		$lifecycle_key            = self::lifecycle_event_key( $event_name, $properties );
		$properties['$insert_id'] = 'bbai_lifecycle:' . $lifecycle_key;

		$queue = get_option( self::LIFECYCLE_QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		foreach ( $queue as $queued_entry ) {
			if ( is_array( $queued_entry ) && isset( $queued_entry['lifecycle_key'] ) && $lifecycle_key === (string) $queued_entry['lifecycle_key'] ) {
				return;
			}
		}

		$queue[] = array(
			'event'         => $event_name,
			'distinct_id'   => '' !== $site_install_id ? $site_install_id : (string) ( $properties['site_hash'] ?? '' ),
			'properties'    => $properties,
			'queued_at'     => gmdate( 'c' ),
			'lifecycle_key' => $lifecycle_key,
		);
		$queue   = array_slice( $queue, -20 );
		update_option( self::LIFECYCLE_QUEUE_OPTION, $queue, false );

		self::emit(
			$event_name,
			array_merge(
				$properties,
				array(
					'lifecycle_delivery' => 'queued',
				)
			)
		);

		self::flush_queued_lifecycle_events();
	}

	/**
	 * Flush queued lifecycle events through the server-side PostHog bridge.
	 */
	public static function flush_queued_lifecycle_events(): void {
		if ( ! self::has_telemetry_consent() ) {
			return;
		}

		$queue = get_option( self::LIFECYCLE_QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return;
		}

		delete_option( self::LIFECYCLE_QUEUE_OPTION );
		foreach ( $queue as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$event_name  = isset( $entry['event'] ) ? sanitize_key( (string) $entry['event'] ) : '';
			$distinct_id = isset( $entry['distinct_id'] ) ? sanitize_text_field( (string) $entry['distinct_id'] ) : '';
			$properties  = isset( $entry['properties'] ) && is_array( $entry['properties'] ) ? $entry['properties'] : array();
			if ( '' === $event_name || '' === $distinct_id ) {
				continue;
			}
			self::capture_posthog_event(
				$event_name,
				$distinct_id,
				array_merge(
					$properties,
					array(
						'transport'          => 'wp_lifecycle_server_bridge',
						'lifecycle_delivery' => 'queued_flush',
					)
				)
			);
		}
	}

	/**
	 * Non-blocking server-side PostHog capture for trustworthy value events.
	 *
	 * @param string $event_name  Event name.
	 * @param string $distinct_id Stable PostHog distinct id.
	 * @param array  $properties  Event properties.
	 */
	public static function capture_posthog_event( string $event_name, string $distinct_id, array $properties = array() ): void {
		$event_name  = sanitize_key( $event_name );
		$event_name  = self::normalize_event_name( $event_name, $properties );
		$distinct_id = sanitize_text_field( $distinct_id );
		if ( '' === $event_name || '' === $distinct_id ) {
			return;
		}

		if ( ! self::has_telemetry_consent() ) {
			return;
		}

		if ( ! apply_filters( 'bbai_posthog_server_capture_enabled', true, $event_name, $distinct_id, $properties ) ) {
			return;
		}

		$api_key  = self::get_posthog_api_key();
		$api_host = self::get_posthog_api_host();
		if ( '' === $api_key || '' === $api_host ) {
			return;
		}

		$properties = self::with_site_identity( self::sanitize_properties( $properties ) );

		$payload = wp_json_encode(
			array(
				'api_key'     => $api_key,
				'event'       => $event_name,
				'distinct_id' => $distinct_id,
				'properties'  => $properties,
			)
		);

		if ( ! is_string( $payload ) || '' === $payload ) {
			return;
		}

		wp_remote_post(
			$api_host . '/capture/',
			array(
				'timeout'     => 1,
				'blocking'    => false,
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => $payload,
				'data_format' => 'body',
			)
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
		$out = array();
		foreach ( $props as $k => $v ) {
			$key = is_string( $k ) ? sanitize_key( $k ) : '';
			if ( is_string( $k ) && in_array( $k, array( '$insert_id', '$set', '$set_once' ), true ) ) {
				$key = $k;
			}
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
				$nested = array();
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

	/**
	 * Add the canonical install identity used to join logged-out, logged-in, and lifecycle events.
	 *
	 * @param array<string,mixed> $props           Sanitized properties.
	 * @param string|null         $site_install_id Optional pre-resolved install id.
	 * @return array<string,mixed>
	 */
	private static function with_site_identity( array $props, ?string $site_install_id = null ): array {
		$site_install_id = is_string( $site_install_id ) ? sanitize_text_field( $site_install_id ) : self::resolve_site_id();
		if ( '' === $site_install_id && function_exists( __NAMESPACE__ . '\\get_site_identifier' ) ) {
			$site_install_id = sanitize_text_field( (string) get_site_identifier() );
		}
		if ( '' === $site_install_id ) {
			return $props;
		}

		if ( empty( $props['site_install_id'] ) ) {
			$props['site_install_id'] = $site_install_id;
		}
		if ( empty( $props['site_id'] ) ) {
			$props['site_id'] = $site_install_id;
		}
		if ( empty( $props['site_hash'] ) ) {
			$props['site_hash'] = hash( 'sha256', $site_install_id );
		}
		if ( empty( $props['site_url'] ) && function_exists( 'home_url' ) ) {
			$props['site_url'] = esc_url_raw( home_url( '/' ) );
		}
		$resolved_host = self::normalize_host_value( $props['host'] ?? $props['site_host'] ?? '' );
		if ( '' === $resolved_host && ! empty( $props['site_url'] ) && function_exists( 'wp_parse_url' ) ) {
			$parsed_host = wp_parse_url( (string) $props['site_url'], PHP_URL_HOST );
			$resolved_host = self::normalize_host_value( is_string( $parsed_host ) ? $parsed_host : '' );
		}
		if ( '' !== $resolved_host ) {
			$props['host'] = $resolved_host;
			if ( empty( $props['site_host'] ) ) {
				$props['site_host'] = $resolved_host;
			} else {
				$normalized_site_host = self::normalize_host_value( $props['site_host'] );
				if ( '' !== $normalized_site_host ) {
					$props['site_host'] = $normalized_site_host;
				}
			}
		}
		if ( empty( $props['plugin_slug'] ) ) {
			$props['plugin_slug'] = self::get_plugin_slug();
		}
		if ( empty( $props['telemetry_version'] ) ) {
			$props['telemetry_version'] = self::TELEMETRY_SCHEMA_VERSION;
		}
		if ( empty( $props['journey_id'] ) ) {
			$props['journey_id'] = $site_install_id;
		}
		if ( empty( $props['session_id'] ) ) {
			$session_id = self::resolve_browser_session_id();
			if ( '' !== $session_id ) {
				$props['session_id'] = $session_id;
			}
		}
		if ( empty( $props['plugin_version'] ) && defined( 'BEEPBEEP_AI_VERSION' ) ) {
			$props['plugin_version'] = (string) BEEPBEEP_AI_VERSION;
		}
		if ( empty( $props['environment'] ) ) {
			$props['environment'] = self::resolve_environment();
		}
		if ( empty( $props['wp_version'] ) && function_exists( 'get_bloginfo' ) ) {
			$props['wp_version'] = (string) get_bloginfo( 'version' );
		}
		if ( empty( $props['wordpress_version'] ) && ! empty( $props['wp_version'] ) ) {
			$props['wordpress_version'] = $props['wp_version'];
		}
		if ( empty( $props['php_version'] ) ) {
			$props['php_version'] = PHP_VERSION;
		}

		$plan = self::normalize_plan_value( $props['plan'] ?? $props['plan_type'] ?? $props['current_plan'] ?? self::resolve_plan_type() );
		if ( 'unknown' === $plan && self::is_plugin_account_logged_in() ) {
			$plan = 'free';
		}
		$props['plan']        = $plan;
		$props['plan_type']   = $plan;
		$props['plugin_plan'] = $plan;
		if ( empty( $props['current_plan'] ) || 'unknown' === $props['current_plan'] ) {
			$props['current_plan'] = $plan;
		}

		$is_logged_in = self::is_plugin_account_logged_in();
		if ( ! isset( $props['is_logged_in'] ) ) {
			$props['is_logged_in'] = $is_logged_in;
		}
		if ( empty( $props['user_state'] ) ) {
			$props['user_state'] = $is_logged_in ? 'signed_in' : 'guest';
		}
		if ( ! isset( $props['is_returning_user'] ) ) {
			$props['is_returning_user'] = self::days_since_last_active() > 0;
		}
		if ( ! isset( $props['is_first_generation'] ) ) {
			$props['is_first_generation'] = false;
		}
		if ( ! isset( $props['credits_remaining'] ) ) {
			$credits_remaining = self::resolve_credits_remaining();
			if ( null !== $credits_remaining ) {
				$props['credits_remaining'] = $credits_remaining;
			}
		}
		if ( empty( $props['quota_state'] ) ) {
			$quota_state = self::resolve_quota_state();
			if ( '' !== $quota_state ) {
				$props['quota_state'] = $quota_state;
			}
		}
		if ( empty( $props['license_state'] ) ) {
			$props['license_state'] = self::resolve_license_state();
		}
		if ( empty( $props['generation_type'] ) && ! empty( $props['generation_mode'] ) ) {
			$props['generation_type'] = sanitize_key( (string) $props['generation_mode'] );
		}
		if ( empty( $props['generation_mode'] ) && ! empty( $props['generation_type'] ) ) {
			$props['generation_mode'] = sanitize_key( (string) $props['generation_type'] );
		}

		return $props;
	}

	/**
	 * Normalize host values to bare hostname (no scheme/path).
	 *
	 * @param mixed $value Raw host or URL value.
	 */
	private static function normalize_host_value( $value ): string {
		$host = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $host ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $host ) && function_exists( 'wp_parse_url' ) ) {
			$parsed = wp_parse_url( $host, PHP_URL_HOST );
			$host   = is_string( $parsed ) ? $parsed : '';
		}
		$host = strtolower( preg_replace( '#/.*$#', '', $host ) );
		return '' !== $host ? sanitize_text_field( $host ) : '';
	}

	/**
	 * Stable dedupe key for queued lifecycle delivery.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $properties Normalized event properties.
	 */
	private static function lifecycle_event_key( string $event_name, array $properties ): string {
		$parts = array(
			$event_name,
			(string) ( $properties['site_install_id'] ?? $properties['site_hash'] ?? '' ),
			(string) ( $properties['previous_version'] ?? $properties['previous_plugin_version'] ?? '' ),
			(string) ( $properties['plugin_version'] ?? '' ),
		);

		return hash( 'sha256', implode( '|', $parts ) );
	}

	private static function resolve_environment(): string {
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return sanitize_key( (string) wp_get_environment_type() );
		}
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			return sanitize_key( (string) WP_ENVIRONMENT_TYPE );
		}
		return 'production';
	}

	/**
	 * Normalize legacy telemetry names into the founder dashboard event contract.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $properties Event properties, updated with required diagnostic fields.
	 */
	private static function normalize_event_name( string $event_name, array &$properties ): string {
		if ( 'generation_failed' === $event_name ) {
			$event_name                 = self::normalize_generation_failure_event( $properties );
			$properties['error_code']   = self::normalize_failure_code( $properties['error_code'] ?? $properties['code'] ?? $event_name );
			$properties['provider']     = isset( $properties['provider'] ) ? sanitize_key( (string) $properties['provider'] ) : 'unknown';
			$properties['retry_attempt'] = isset( $properties['retry_attempt'] ) ? max( 0, (int) $properties['retry_attempt'] ) : ( isset( $properties['retry_count'] ) ? max( 0, (int) $properties['retry_count'] ) : 0 );
			if ( empty( $properties['response_time'] ) ) {
				$properties['response_time'] = $properties['response_time_ms'] ?? $properties['processing_time_ms'] ?? $properties['generation_latency_ms'] ?? '';
			}
			return $event_name;
		}

		$aliases = array(
			'guest_dashboard_viewed'   => 'dashboard_viewed',
			'upgrade_clicked'          => 'upgrade_cta_clicked',
			'upgrade_started'          => 'upgrade_cta_clicked',
			'upgrade_completed'        => 'checkout_completed',
			'checkout_session_created' => 'checkout_started',
			'account_created'          => 'signup_succeeded',
			'first_alt_generated'      => 'first_run_completed',
			'manual_edit_used'         => 'manual_alt_edit',
			'alt_generated_success'    => 'generation_completed',
			'alt_generated_failed'     => 'generation_failed_unknown',
		);

		if ( isset( $aliases[ $event_name ] ) ) {
			if ( 'guest_dashboard_viewed' === $event_name && empty( $properties['user_state'] ) ) {
				$properties['user_state'] = 'guest';
			}
			$event_name = $aliases[ $event_name ];
		}

		if ( 'feature_used' === $event_name ) {
			$feature_name = self::normalize_feature_name( $properties['feature_name'] ?? $properties['feature'] ?? '' );
			if ( '' === $feature_name ) {
				return '';
			}
			$properties['feature_name'] = $feature_name;
			unset( $properties['feature'] );
		}

		if ( 0 === strpos( $event_name, 'generation_failed_' ) ) {
			$properties['error_code']    = self::normalize_failure_code( $properties['error_code'] ?? $properties['code'] ?? $event_name );
			$properties['provider']      = isset( $properties['provider'] ) ? sanitize_key( (string) $properties['provider'] ) : 'unknown';
			$properties['retry_attempt'] = isset( $properties['retry_attempt'] ) ? max( 0, (int) $properties['retry_attempt'] ) : ( isset( $properties['retry_count'] ) ? max( 0, (int) $properties['retry_count'] ) : 0 );
		}
		if ( in_array( $event_name, array( 'generation_started', 'generation_completed', 'alt_generated', 'generation_blocked_no_credits' ), true ) && empty( $properties['generation_mode'] ) ) {
			$properties['generation_mode'] = 'single';
		}
		if ( 0 === strpos( $event_name, 'batch_generation_' ) && empty( $properties['generation_mode'] ) ) {
			$properties['generation_mode'] = 'bulk';
		}
		if (
			in_array( $event_name, array( 'generation_started', 'generation_completed', 'alt_generated', 'generation_blocked_no_credits' ), true )
			|| 0 === strpos( $event_name, 'generation_failed_' )
			|| 0 === strpos( $event_name, 'batch_generation_' )
		) {
			if ( empty( $properties['generation_type'] ) && ! empty( $properties['generation_mode'] ) ) {
				$properties['generation_type'] = sanitize_key( (string) $properties['generation_mode'] );
			}
			if ( empty( $properties['feature_name'] ) ) {
				$mode = sanitize_key( (string) ( $properties['generation_mode'] ?? $properties['generation_type'] ?? 'single' ) );
				$properties['feature_name'] = 'bulk' === $mode ? 'bulk_generation' : 'single_generation';
			}
		}
		if ( in_array( $event_name, array( 'signup_started', 'signup_succeeded', 'signup_cta_clicked' ), true ) && empty( $properties['feature_name'] ) ) {
			$properties['feature_name'] = 'signup';
		}
		if ( in_array( $event_name, array( 'login_succeeded', 'login_cta_clicked', 'login_modal_opened', 'login_submitted', 'login_failed' ), true ) && empty( $properties['feature_name'] ) ) {
			$properties['feature_name'] = 'login';
		}
		if ( 'review_queue_opened' === $event_name && empty( $properties['feature_name'] ) ) {
			$properties['feature_name'] = 'review_queue';
		}
		if ( 'generation_blocked_no_credits' === $event_name && empty( $properties['feature_name'] ) ) {
			$properties['feature_name'] = 'quota';
		}

		return $event_name;
	}

	/**
	 * @param array<string,mixed> $properties Event properties.
	 */
	private static function normalize_generation_failure_event( array $properties ): string {
		$code    = self::normalize_failure_code( $properties['error_code'] ?? $properties['code'] ?? $properties['error'] ?? $properties['status_code'] ?? $properties['http_status'] ?? '' );
		$message = strtolower( sanitize_text_field( (string) ( $properties['error_message'] ?? $properties['message'] ?? '' ) ) );
		$signal  = $code . ' ' . $message;

		if ( preg_match( '/timeout|timed_out|deadline|504|gateway_timeout/', $signal ) ) {
			return 'generation_failed_timeout';
		}
		if ( preg_match( '/auth|unauthori[sz]ed|forbidden|invalid_?key|session|login|401|403/', $signal ) ) {
			return 'generation_failed_auth';
		}
		if ( preg_match( '/credit|quota|limit_?reached|insufficient|exhausted|no_?credits|payment_required|402/', $signal ) ) {
			return 'generation_failed_no_credits';
		}
		if ( preg_match( '/invalid_?(image|attachment|mime)|unsupported|corrupt|too_?large|file_?type|media/', $signal ) ) {
			return 'generation_failed_invalid_image';
		}
		if ( preg_match( '/rate_?limit|too_?many|429/', $signal ) ) {
			return 'generation_failed_rate_limit';
		}
		if ( preg_match( '/network|offline|fetch|connection|dns|ssl|abort/', $signal ) ) {
			return 'generation_failed_network';
		}
		if ( preg_match( '/api|provider|openai|server|5\d\d|bad_gateway|service_unavailable/', $signal ) ) {
			return 'generation_failed_api';
		}

		return 'generation_failed_unknown';
	}

	private static function normalize_failure_code( $value ): string {
		$code = strtolower( sanitize_key( is_scalar( $value ) ? (string) $value : '' ) );
		return '' !== $code ? substr( $code, 0, 120 ) : 'unknown';
	}

	private static function normalize_feature_name( $value ): string {
		$feature = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
		$feature = str_replace( '-', '_', $feature );
		$aliases = array(
			'alt_generation'           => 'single_generation',
			'generation'               => 'single_generation',
			'generate'                 => 'single_generation',
			'single'                   => 'single_generation',
			'bulk'                     => 'bulk_generation',
			'batch'                    => 'bulk_generation',
			'review_workflow'          => 'review',
			'alt_library'              => 'library',
			'analytics'                => 'statistics',
			'stats'                    => 'statistics',
			'woocommerce_optimisation' => 'woocommerce',
			'woocommerce_optimization' => 'woocommerce',
			'queue'                    => 'review_queue',
			'auth'                     => 'account',
		);
		if ( isset( $aliases[ $feature ] ) ) {
			$feature = $aliases[ $feature ];
		}
		$allowed = array(
			'dashboard',
			'library',
			'single_generation',
			'bulk_generation',
			'review',
			'review_queue',
			'settings',
			'statistics',
			'woocommerce',
			'billing',
			'account',
			'login',
			'signup',
			'quota',
		);
		return in_array( $feature, $allowed, true ) ? $feature : '';
	}

	private static function resolve_quota_state(): string {
		if ( ! class_exists( Usage_Tracker::class ) ) {
			return '';
		}
		$stats = Usage_Tracker::get_stats_display();
		foreach ( array( 'quota_state' ) as $key ) {
			if ( isset( $stats[ $key ] ) && is_scalar( $stats[ $key ] ) ) {
				$state = sanitize_key( (string) $stats[ $key ] );
				if ( '' !== $state ) {
					return $state;
				}
			}
		}
		if ( isset( $stats['quota'] ) && is_array( $stats['quota'] ) && isset( $stats['quota']['quota_state'] ) && is_scalar( $stats['quota']['quota_state'] ) ) {
			$state = sanitize_key( (string) $stats['quota']['quota_state'] );
			if ( '' !== $state ) {
				return $state;
			}
		}
		return '';
	}

	private static function resolve_license_state(): string {
		if ( ! self::is_plugin_account_logged_in() ) {
			return 'guest';
		}
		if ( function_exists( 'get_option' ) ) {
			$license_key  = (string) get_option( 'beepbeepai_license_key', '' );
			$license_data = get_option( 'beepbeepai_license_data', array() );
			if ( '' !== $license_key && ! empty( $license_data ) ) {
				return 'licensed';
			}
		}
		return 'connected';
	}

	private static function resolve_browser_session_id(): string {
		if ( empty( $_COOKIE['bbai_session_id'] ) || ! is_scalar( $_COOKIE['bbai_session_id'] ) ) {
			return '';
		}
		$session_id = sanitize_key( wp_unslash( (string) $_COOKIE['bbai_session_id'] ) );
		return preg_match( '/^[a-z0-9_-]{8,80}$/', $session_id ) ? $session_id : '';
	}

	private static function resolve_credits_remaining(): ?int {
		if ( ! class_exists( Usage_Tracker::class ) ) {
			return null;
		}
		$stats = Usage_Tracker::get_stats_display();
		foreach ( array( 'credits_remaining', 'remaining' ) as $key ) {
			if ( isset( $stats[ $key ] ) && is_numeric( $stats[ $key ] ) ) {
				return max( 0, (int) $stats[ $key ] );
			}
		}
		if ( isset( $stats['quota'] ) && is_array( $stats['quota'] ) && isset( $stats['quota']['remaining'] ) && is_numeric( $stats['quota']['remaining'] ) ) {
			return max( 0, (int) $stats['quota']['remaining'] );
		}
		return null;
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
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Trial_Quota' ) && Trial_Quota::is_trial_user() ) {
			return 'trial';
		}
		if ( ! class_exists( Usage_Tracker::class ) ) {
			return self::is_plugin_account_logged_in() ? 'free' : 'unknown';
		}
		$stats = Usage_Tracker::get_stats_display();
		$plan  = self::normalize_plan_value( $stats['plan'] ?? $stats['plan_type'] ?? '' );
		if ( 'unknown' === $plan && self::is_plugin_account_logged_in() ) {
			return 'free';
		}
		return $plan;
	}

	private static function is_plugin_account_logged_in(): bool {
		if ( function_exists( '\bbai_is_authenticated' ) ) {
			return (bool) \bbai_is_authenticated();
		}
		if ( function_exists( 'get_option' ) ) {
			return '' !== (string) get_option( 'beepbeepai_jwt_token', '' )
				|| '' !== (string) get_option( 'opptibbai_jwt_token', '' )
				|| '' !== (string) get_option( 'beepbeepai_license_key', '' )
				|| ! empty( get_option( 'beepbeepai_license_data', array() ) );
		}
		return false;
	}

	private static function resolve_page_slug(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$map  = array(
			'bbai'                 => 'dashboard',
			'bbai-library'         => 'alt_library',
			'bbai-analytics'       => 'analytics',
			'bbai-credit-usage'    => 'usage',
			'bbai-settings'        => 'settings',
			'bbai-debug'           => 'settings',
			'bbai-guide'           => 'help',
			'bbai-onboarding'      => 'onboarding',
			'bbai-ui-kit'          => 'ui_kit',
			'bbai-agency-overview' => 'agency_overview',
		);
		if ( isset( $map[ $page ] ) ) {
			return $map[ $page ];
		}
		return '' !== $page ? 'other' : 'unknown';
	}

	/**
	 * @param array<string,mixed> $envelope Envelope to store.
	 */
	private static function push_ring( array $envelope ): void {
		$ring = get_option( self::RING_OPTION, array() );
		if ( ! is_array( $ring ) ) {
			$ring = array();
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
// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- convenience wrapper kept in same file as its class.
function bbai_telemetry_emit( string $event_name, array $properties = array() ): void {
	BBAI_Telemetry::emit( $event_name, $properties );
}
