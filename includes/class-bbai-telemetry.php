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

	/**
	 * Set when a site admin saves the usage analytics choice in Settings.
	 * Distinguishes a real opt-in from the 'yes' older builds wrote on activation.
	 */
	public const CONSENT_SOURCE_OPTION = 'bbai_telemetry_consent_source';

	/**
	 * Marks that the one-time reset of activation-implied consent has run.
	 */
	public const CONSENT_RESET_MARKER_OPTION = 'bbai_telemetry_consent_reset_4_6_142';

	/**
	 * admin-post action for the Settings usage analytics toggle.
	 */
	public const CONSENT_FORM_ACTION = 'bbai_save_usage_analytics';

	public const LIFECYCLE_QUEUE_OPTION = 'bbai_telemetry_lifecycle_queue';

	public const INSTALLED_MARKER_OPTION = 'bbai_telemetry_plugin_installed';

	public const LEGACY_INSTALLED_MARKER_OPTION = 'bbai_telemetry_plugin_installed_logged';

	public const ACTIVATION_MARKER_OPTION = 'bbai_telemetry_plugin_activated';

	/** Other OpptiAI plugins active at first activation (comma list of ids, local only). */
	public const ACTIVATION_PLUGINS_OPTION = 'bbai_activation_oppti_plugins';

	/** Set once the opt-in lifecycle snapshot has been sent for this site. */
	public const OPT_IN_LIFECYCLE_SENT_OPTION = 'bbai_telemetry_opt_in_lifecycle_sent';

	/**
	 * Property keys that identify the site or a page on it and are never sent to
	 * PostHog. Matched at any depth (including $set / $set_once), case-insensitive,
	 * with any leading "$" ignored, so $current_url and $referrer match too.
	 */
	public const SITE_IDENTIFIER_KEYS = array(
		'site_url',
		'site_host',
		'host',
		'home_url',
		'domain',
		'siteurl',
		'website',
		'referrer',
		'initial_referrer',
		'page_url',
		'current_url',
		'initial_current_url',
		'url',
		'pathname',
		'initial_pathname',
		'hostname',
		'initial_host',
		'referring_domain',
		'initial_referring_domain',
		'landing_page',
		'href',
		'origin',
	);

	/**
	 * Key endings that also mark a site, page or link value (site_url, checkout_url,
	 * referrer_host, current_domain and so on). Matched like SITE_IDENTIFIER_KEYS.
	 */
	public const SITE_IDENTIFIER_KEY_SUFFIXES = array(
		'_url',
		'_uri',
		'_href',
		'_host',
		'_hostname',
		'_domain',
		'_referrer',
		'_pathname',
	);

	/**
	 * Keys whose values are this plugin's own opaque ids or labels and are always kept.
	 */
	private const SITE_IDENTIFIER_KEEP_KEYS = array( 'site_hash', 'site_install_id', 'site_id', 'plugin_slug', '$insert_id', 'journey_id', 'session_id' );

	/** Domain suffixes and host patterns that mark a local, staging or sandbox site. */
	private const TEST_HOST_PATTERN = '/(^localhost$|^127\.\d+\.\d+\.\d+$|^::1$|^\[::1\]$|\.local$|\.test$|\.localhost$|\.invalid$|\.example$|(^|\.)example\.(com|org|net)$|(^|[.-])(staging|stg)([.-]|\d|$)|(staging|stg)\d*\.wpengine(powered)?\.com$|\.wpenginestaging\.com$|(staging|stg|tempurl).*\.godaddysites\.com$|\.myftpupload\.com$|(^|\.)tempurl\.|instawp|tastewp|playground|localhost|127\.0\.0\.1|wpsandbox|wp-env|trycloudflare|ngrok)/i';

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
		$properties      = self::with_site_identity( self::sanitize_properties( $properties ), $site_install_id, $event_name );

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

	/** First saved generation per consenting installation, including cron/bulk. */
	public static function first_value_properties(): array {
		if ( ! self::has_telemetry_consent() || ! in_array( get_option( self::CONSENT_OPTION, false ), array( 'yes', '1', 1, true ), true ) ) {
			return array();
		}
		$now = time();
		$first = add_option( 'bbai_telemetry_first_generation_at', $now, '', false );
		$props     = array( 'is_first_generation' => $first );
		$activated = get_option( self::ACTIVATION_MARKER_OPTION, '' );
		$started   = is_numeric( $activated ) ? (int) $activated : strtotime( (string) $activated );
		if ( $first && $started > 0 && $now >= $started ) {
			$props['time_to_first_value_seconds'] = max( 0, $now - $started );
		}
		return $props;
	}

	/**
	 * Whether this site has opted in to external product analytics.
	 */
	public static function has_telemetry_consent(): bool {
		if ( defined( 'BBAI_TELEMETRY_CONSENT' ) ) {
			return (bool) BBAI_TELEMETRY_CONSENT;
		}

		$stored = get_option( self::CONSENT_OPTION, false );
		// Opt-in only: nothing is sent unless the site owner has said yes in
		// Settings. A 'yes' written automatically by older builds on activation
		// is not consent, even before the one-time reset has run.
		$granted = in_array( $stored, array( 'yes', '1', 1, true ), true )
			&& 'settings' === get_option( self::CONSENT_SOURCE_OPTION, '' );

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
		// Starter is its own paid plan: report it as starter, not pro (Growth).
		if ( in_array( $plan, array( 'free', 'trial', 'starter', 'pro', 'agency' ), true ) ) {
			return $plan;
		}
		if ( 'anonymous_trial' === $plan ) {
			return 'trial';
		}
		if ( in_array( $plan, array( 'growth', 'enterprise' ), true ) ) {
			return 'pro';
		}
		return 'unknown';
	}

	/**
	 * Whether props describe a checkout / upgrade intent (the plan in `plan` or
	 * `target_plan` is the plan being bought, not the account's plan).
	 *
	 * @param array<string,mixed> $props      Event properties.
	 * @param string              $event_name Normalized event name, when known.
	 */
	private static function is_purchase_intent_event( array $props, string $event_name = '' ): bool {
		if ( ! empty( $props['target_plan'] ) ) {
			return true;
		}
		return '' !== $event_name && ( 0 === strpos( $event_name, 'checkout_' ) || 0 === strpos( $event_name, 'upgrade_' ) );
	}

	/**
	 * Whether the site admin has turned usage analytics on (stored choice only,
	 * ignoring constant/filter overrides). Used to render the Settings toggle.
	 */
	public static function stored_consent_is_yes(): bool {
		return in_array( get_option( self::CONSENT_OPTION, false ), array( 'yes', '1', 1, true ), true );
	}

	/**
	 * Save the site admin's usage analytics choice.
	 *
	 * @param bool $opt_in True to opt in, false to opt out.
	 */
	public static function set_consent( bool $opt_in ): void {
		update_option( self::CONSENT_OPTION, $opt_in ? 'yes' : 'no', false );
		update_option( self::CONSENT_SOURCE_OPTION, 'settings', false );
		if ( ! $opt_in ) {
			// Nothing raised before or after revocation may be sent later.
			delete_option( self::LIFECYCLE_QUEUE_OPTION );
			return;
		}
		self::record_opt_in_lifecycle();
	}

	/**
	 * Send plugin_installed / plugin_activated once, at the moment of opt-in.
	 *
	 * Lifecycle events raised before consent are discarded, never replayed. This
	 * snapshot is a new event timestamped at opt-in (its own true time). The
	 * locally recorded install/activation time travels only as first_seen_at /
	 * activated_at, and lifecycle_delivery=opt_in_snapshot marks it honestly so
	 * reports never treat it as a live activation.
	 */
	public static function record_opt_in_lifecycle(): void {
		if ( ! self::has_telemetry_consent() || get_option( self::OPT_IN_LIFECYCLE_SENT_OPTION, false ) ) {
			return;
		}
		$site_install_id = self::resolve_site_id();
		if ( '' === $site_install_id ) {
			return;
		}
		update_option( self::OPT_IN_LIFECYCLE_SENT_OPTION, gmdate( 'c' ), false );

		$opted_in_at  = gmdate( 'c' );
		$first_seen   = self::marker_time( get_option( self::INSTALLED_MARKER_OPTION, '' ) );
		$activated_at = self::marker_time( get_option( self::ACTIVATION_MARKER_OPTION, '' ) );
		$events       = array(
			'plugin_installed' => array( 'first_seen_at' => $first_seen ),
			'plugin_activated' => array_merge(
				array( 'activated_at' => $activated_at ),
				self::stored_activation_plugins()
			),
		);
		foreach ( $events as $event_name => $extra ) {
			$properties = array_merge(
				array(
					'lifecycle_event'    => $event_name,
					'lifecycle_delivery' => 'opt_in_snapshot',
					'recorded_at_opt_in' => true,
					'opted_in_at'        => $opted_in_at,
					'occurred_at'        => $opted_in_at,
					'$insert_id'         => 'bbai_opt_in_lifecycle:' . hash( 'sha256', $event_name . '|' . $site_install_id ),
				),
				array_filter( $extra )
			);
			self::capture_posthog_event( $event_name, $site_install_id, $properties, $opted_in_at );
		}
	}

	/**
	 * Normalise a stored marker (ISO string or unix time) to ISO 8601, or ''.
	 *
	 * @param mixed $value Stored marker value.
	 */
	private static function marker_time( $value ): string {
		if ( is_numeric( $value ) && (int) $value > 1000000000 ) {
			return gmdate( 'c', (int) $value );
		}
		if ( is_string( $value ) && '' !== $value && '1' !== $value ) {
			$ts = strtotime( $value );
			return false !== $ts ? gmdate( 'c', $ts ) : '';
		}
		return '';
	}

	/**
	 * One-time reset: builds before 4.6.141 wrote consent = 'yes' on activation
	 * without asking. That is not an opt-in, so clear it unless an admin chose it
	 * in Settings. Runs once per site on admin_init.
	 */
	public static function maybe_reset_implied_consent(): void {
		if ( get_option( self::CONSENT_RESET_MARKER_OPTION, false ) ) {
			return;
		}
		if ( 'settings' !== get_option( self::CONSENT_SOURCE_OPTION, '' ) && false !== get_option( self::CONSENT_OPTION, false ) ) {
			delete_option( self::CONSENT_OPTION );
		}
		update_option( self::CONSENT_RESET_MARKER_OPTION, gmdate( 'c' ), false );
	}

	/**
	 * admin-post handler for the Settings usage analytics form.
	 */
	public static function handle_consent_form(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change this setting.', 'beepbeep-ai-alt-text-generator' ), 403 );
		}
		check_admin_referer( self::CONSENT_FORM_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
		$opt_in = isset( $_POST['bbai_usage_analytics'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['bbai_usage_analytics'] ) );
		self::set_consent( $opt_in );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=bbai-settings' );
		}
		wp_safe_redirect( add_query_arg( 'bbai_usage_analytics_saved', $opt_in ? 'on' : 'off', remove_query_arg( 'bbai_usage_analytics_saved', $redirect ) ) );
		exit;
	}

	/**
	 * Which OpptiAI plugins are installed right now, as four booleans
	 * (has_alt_text, has_titles, has_il, has_auditor). Local check only.
	 *
	 * @return array<string,bool>
	 */
	public static function activation_plugins(): array {
		if ( class_exists( BBAI_More_From_Oppti::class ) ) {
			return BBAI_More_From_Oppti::installed_flags();
		}
		return array();
	}

	/**
	 * The flags saved at first activation, booleans only.
	 *
	 * @return array<string,bool>
	 */
	private static function stored_activation_plugins(): array {
		$stored = get_option( self::ACTIVATION_PLUGINS_OPTION, array() );
		$out    = array();
		if ( is_array( $stored ) ) {
			foreach ( array( 'has_alt_text', 'has_titles', 'has_il', 'has_auditor' ) as $key ) {
				if ( isset( $stored[ $key ] ) ) {
					$out[ $key ] = (bool) $stored[ $key ];
				}
			}
		}
		return $out;
	}

	/**
	 * Capture activation/update/install semantics from the real WordPress lifecycle hook.
	 */
	public static function record_activation_lifecycle(): void {
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
			// Which OpptiAI plugins are installed at first activation (four booleans, no names or paths).
			$oppti_plugins = self::activation_plugins();
			update_option( self::ACTIVATION_PLUGINS_OPTION, $oppti_plugins, false );
			self::queue_lifecycle_event( 'plugin_activated', $oppti_plugins );
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
	 * Record a lifecycle event raised by a WordPress hook.
	 *
	 * Without consent at the moment the event happens it is discarded: nothing is
	 * stored for later delivery. With consent it is queued with its own
	 * occurred_at and flushed straight away (or on the next admin request).
	 *
	 * @param string $event_name Lifecycle event name.
	 * @param array  $properties Event properties.
	 */
	public static function queue_lifecycle_event( string $event_name, array $properties = array() ): void {
		$event_name = sanitize_key( $event_name );
		if ( ! in_array( $event_name, array( 'plugin_installed', 'plugin_activated', 'plugin_updated', 'plugin_deactivated' ), true ) ) {
			return;
		}

		if ( ! self::has_telemetry_consent() ) {
			return;
		}

		$occurred_at     = gmdate( 'c' );
		$site_install_id = self::resolve_site_id();

		$properties = self::with_site_identity(
			self::sanitize_properties(
				array_merge(
					array(
						'lifecycle_event' => $event_name,
						'occurred_at'     => $occurred_at,
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
			'event'              => $event_name,
			'distinct_id'        => '' !== $site_install_id ? $site_install_id : (string) ( $properties['site_hash'] ?? '' ),
			'properties'         => $properties,
			'queued_at'          => $occurred_at,
			'occurred_at'        => $occurred_at,
			'consented_at_raise' => true,
			'lifecycle_key'      => $lifecycle_key,
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
	 *
	 * Only entries that had consent when they were raised are sent, each with its
	 * original occurred_at as the PostHog timestamp. Anything else (including
	 * entries queued by older builds without consent) is dropped.
	 */
	public static function flush_queued_lifecycle_events(): void {
		$queue = get_option( self::LIFECYCLE_QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return;
		}

		if ( ! self::has_telemetry_consent() ) {
			delete_option( self::LIFECYCLE_QUEUE_OPTION );
			return;
		}

		delete_option( self::LIFECYCLE_QUEUE_OPTION );
		foreach ( $queue as $entry ) {
			if ( ! is_array( $entry ) || true !== ( $entry['consented_at_raise'] ?? false ) ) {
				continue;
			}
			$event_name  = isset( $entry['event'] ) ? sanitize_key( (string) $entry['event'] ) : '';
			$distinct_id = isset( $entry['distinct_id'] ) ? sanitize_text_field( (string) $entry['distinct_id'] ) : '';
			$properties  = isset( $entry['properties'] ) && is_array( $entry['properties'] ) ? $entry['properties'] : array();
			$occurred_at = isset( $entry['occurred_at'] ) ? (string) $entry['occurred_at'] : '';
			if ( '' === $event_name || '' === $distinct_id || '' === $occurred_at ) {
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
						'occurred_at'        => $occurred_at,
					)
				),
				$occurred_at
			);
		}
	}

	/**
	 * Non-blocking server-side PostHog capture for trustworthy value events.
	 *
	 * @param string $event_name  Event name.
	 * @param string $distinct_id Stable PostHog distinct id.
	 * @param array  $properties  Event properties.
	 * @param string $timestamp   Optional ISO 8601 time the event happened (defaults to now).
	 */
	public static function capture_posthog_event( string $event_name, string $distinct_id, array $properties = array(), string $timestamp = '' ): void {
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

		$properties  = self::strip_site_identifiers( self::scrub_raw_site_identifier( self::with_site_identity( self::sanitize_properties( $properties ), null, $event_name ) ) );
		$distinct_id = (string) self::scrub_raw_site_identifier( $distinct_id );

		$body = array(
			'api_key'     => $api_key,
			'event'       => $event_name,
			'distinct_id' => $distinct_id,
			'properties'  => $properties,
		);
		if ( '' !== $timestamp && false !== strtotime( $timestamp ) ) {
			$body['timestamp'] = gmdate( 'c', (int) strtotime( $timestamp ) );
		}
		$payload = wp_json_encode( $body );

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
	 * Remove site-identifying keys (SITE_IDENTIFIER_KEYS and SITE_IDENTIFIER_KEY_SUFFIXES)
	 * and any URL, host, IP, email or path value, at every depth.
	 *
	 * @param array<mixed> $props Properties, possibly nested ($set, $set_once, context arrays).
	 * @return array<mixed>
	 */
	public static function strip_site_identifiers( array $props ): array {
		foreach ( $props as $key => $value ) {
			if ( is_string( $key ) && self::is_site_identifier_key( $key ) ) {
				unset( $props[ $key ] );
				continue;
			}
			if ( is_array( $value ) ) {
				$props[ $key ] = self::strip_site_identifiers( $value );
				continue;
			}
			if ( is_string( $value ) && ! ( is_string( $key ) && in_array( $key, self::SITE_IDENTIFIER_KEEP_KEYS, true ) ) ) {
				$is_version = is_string( $key ) && 'version' === substr( strtolower( $key ), -7 );
				if ( self::is_url_shaped( $value, $is_version ) ) {
					unset( $props[ $key ] );
				}
			}
		}
		return $props;
	}

	/**
	 * Whether a property key names a site, host, page or link value.
	 *
	 * @param string $key Property key.
	 */
	public static function is_site_identifier_key( string $key ): bool {
		$normalized = strtolower( ltrim( $key, '$' ) );
		if ( in_array( $normalized, self::SITE_IDENTIFIER_KEYS, true ) ) {
			return true;
		}
		foreach ( self::SITE_IDENTIFIER_KEY_SUFFIXES as $suffix ) {
			if ( strlen( $normalized ) > strlen( $suffix ) && substr( $normalized, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a value looks like a URL, host name, IP address, email address or URL path.
	 *
	 * @param string $value      Value to test.
	 * @param bool   $is_version True for version strings (dotted numbers are allowed there).
	 */
	public static function is_url_shaped( string $value, bool $is_version = false ): bool {
		$value = trim( $value );
		if ( '' === $value ) {
			return false;
		}
		$patterns = array(
			'#[a-z][a-z0-9+.\-]*://#i', // Any scheme.
			'#^//#',                    // Protocol-relative URL.
			'#^/[^\s]*$#',              // A URL path such as /wp-admin/upload.php.
			'/\bwww\./i',
			'/\blocalhost\b/i',
		);
		if ( ! $is_version ) {
			// Host name or email domain (letters-only last label, so 4.6.145 is not a host).
			$patterns[] = '/(?<![a-z0-9_-])(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}(?![a-z0-9_-])/i';
			// IPv4 address.
			$patterns[] = '/(?<![\d.])\d{1,3}(?:\.\d{1,3}){3}(?![\d.])/';
		}
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}
		if ( ! $is_version ) {
			// IPv6 address: a hex-and-colon token with "::" or at least five colons.
			foreach ( preg_split( '/[\s,;()"\'<>]+/', $value ) as $token ) {
				$token = trim( (string) $token, '[]' );
				if ( preg_match( '/^[0-9a-f:.%]+$/i', $token ) && substr_count( $token, ':' ) >= 2 && ( false !== strpos( $token, '::' ) || substr_count( $token, ':' ) >= 5 ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Other spellings of a site id a payload may carry (sanitize_key lowercases keys). */
	private const SITE_ID_ALIAS_KEYS = array( 'siteInstallId', 'siteinstallid', 'install_id', 'installId', 'installid', 'siteId', 'siteid', 'siteHash', 'sitehash', 'site' );

	/**
	 * Set site_install_id, site_id and site_hash from this site's own values,
	 * replacing whatever a caller, a browser payload or an API response put there.
	 * Also applied inside $set / $set_once person properties.
	 *
	 * @param array<string,mixed> $props           Properties.
	 * @param string              $site_install_id Local install id ('' drops the keys).
	 * @return array<string,mixed>
	 */
	private static function with_local_site_identity( array $props, string $site_install_id ): array {
		$local = array(
			'site_install_id' => $site_install_id,
			'site_id'         => $site_install_id,
			'site_hash'       => '' === $site_install_id ? '' : self::resolve_site_hash(),
		);
		foreach ( self::SITE_ID_ALIAS_KEYS as $alias ) {
			unset( $props[ $alias ] );
		}
		foreach ( $local as $key => $value ) {
			if ( '' === $value ) {
				unset( $props[ $key ] );
			} else {
				$props[ $key ] = $value;
			}
		}
		foreach ( array( '$set', '$set_once' ) as $person_key ) {
			if ( ! isset( $props[ $person_key ] ) || ! is_array( $props[ $person_key ] ) ) {
				continue;
			}
			foreach ( self::SITE_ID_ALIAS_KEYS as $alias ) {
				unset( $props[ $person_key ][ $alias ] );
			}
			foreach ( $local as $key => $value ) {
				if ( ! array_key_exists( $key, $props[ $person_key ] ) ) {
					continue;
				}
				if ( '' === $value ) {
					unset( $props[ $person_key ][ $key ] );
				} else {
					$props[ $person_key ][ $key ] = $value;
				}
			}
		}
		return $props;
	}

	/**
	 * Add the canonical install identity used to join logged-out, logged-in, and lifecycle events.
	 *
	 * @param array<string,mixed> $props           Sanitized properties.
	 * @param string|null         $site_install_id Optional pre-resolved install id.
	 * @return array<string,mixed>
	 */
	private static function with_site_identity( array $props, ?string $site_install_id = null, string $event_name = '' ): array {
		// The site URL, hostname and page URLs never leave the site; the install id is
		// the only identity. Strip before and after enrichment so no path can skip it.
		$props = self::strip_site_identifiers( self::build_site_identity( self::strip_site_identifiers( $props ), $site_install_id, $event_name ) );
		$props['is_test'] = self::is_test_site();
		foreach ( array( '$set', '$set_once' ) as $person_key ) {
			if ( isset( $props[ $person_key ] ) && is_array( $props[ $person_key ] ) ) {
				$props[ $person_key ]['is_test'] = $props['is_test'];
			}
		}
		return $props;
	}

	/**
	 * Enrich stripped properties with the canonical telemetry contract.
	 *
	 * @param array<string,mixed> $props           Stripped properties.
	 * @param string|null         $site_install_id Optional pre-resolved install id.
	 * @param string              $event_name      Event name.
	 * @return array<string,mixed>
	 */
	private static function build_site_identity( array $props, ?string $site_install_id, string $event_name ): array {

		$site_install_id = is_string( $site_install_id ) ? sanitize_text_field( $site_install_id ) : self::resolve_site_id();
		$props           = self::with_local_site_identity( $props, $site_install_id );
		if ( '' === $site_install_id ) {
			return $props;
		}
		if ( empty( $props['plugin_slug'] ) ) {
			$props['plugin_slug'] = self::get_plugin_slug();
		}
		$props['product']           = 'alt_text';
		$props['telemetry_consent'] = self::has_telemetry_consent();
		$props['telemetry_source']  = 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- contract value.
		$props['telemetry_schema']  = 'opptiai_usage_v2';
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

		// Checkout / upgrade events carry the plan being bought. Keep it in target_plan and
		// keep `plan` as the account's current plan (a Free user buying Starter is not `pro`).
		$plan_input = $props['plan'] ?? $props['plan_type'] ?? $props['current_plan'] ?? self::resolve_plan_type();
		if ( self::is_purchase_intent_event( $props, $event_name ) ) {
			if ( empty( $props['target_plan'] ) && ! empty( $props['plan'] ) && is_scalar( $props['plan'] ) ) {
				$props['target_plan'] = sanitize_key( (string) $props['plan'] );
			}
			$current_input = $props['current_plan'] ?? '';
			$plan_input    = ( is_scalar( $current_input ) && '' !== (string) $current_input && 'unknown' !== (string) $current_input )
				? $current_input
				: self::resolve_plan_type();
		}
		$plan = self::normalize_plan_value( $plan_input );
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

	/** Consent-aware API context; credentials never stand in for telemetry identity. */
	public static function request_telemetry_headers(): array {
		if ( ! self::has_telemetry_consent() ) {
			return array( 'X-Telemetry-Consent' => 'no' );
		}
		return array(
			'X-Telemetry-Consent'    => 'yes',
			// Our own API only (like X-Site-Key); the backend hashes it before analytics.
			'X-Telemetry-Install-ID' => self::resolve_raw_site_identifier(),
			'X-Environment'          => self::resolve_environment(),
			'X-Telemetry-Test'       => self::is_test_site() ? 'yes' : 'no',
		);
	}

	/**
	 * Whether this is a local, staging or sandbox site (environment type or a generic
	 * host pattern). Computed locally; only the boolean is ever sent, never the host.
	 * Known test sites are matched by site_hash in analytics, not by any email.
	 */
	public static function is_test_site(): bool {
		if ( defined( 'BBAI_TELEMETRY_TEST' ) && BBAI_TELEMETRY_TEST ) {
			return true;
		}
		if ( in_array( self::resolve_environment(), array( 'local', 'development', 'staging' ), true ) ) {
			return true;
		}
		$host = function_exists( 'home_url' ) ? strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) : '';
		if ( '' !== $host && preg_match( self::TEST_HOST_PATTERN, $host ) ) {
			return true;
		}
		return false;
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
			'bulk_generation_started'  => 'batch_generation_started',
			'account_created'          => 'signup_succeeded',
			'first_alt_generated'      => 'first_run_completed',
			'manual_edit_used'         => 'manual_alt_edit',
			'alt_generated_success'    => 'generation_completed',
			'alt_generated_failed'     => 'generation_failed',
			'generation_failed_unknown' => 'generation_failed',
		);

		if ( isset( $aliases[ $event_name ] ) ) {
			if ( 'guest_dashboard_viewed' === $event_name && empty( $properties['user_state'] ) ) {
				$properties['user_state'] = 'guest';
			}
			$event_name = $aliases[ $event_name ];
		}

		if ( 'generation_failed' === $event_name ) {
			$properties['error_code'] = self::normalize_failure_code( $properties['error_code'] ?? $properties['code'] ?? 'internal_unknown_error' );
			$properties['provider'] = isset( $properties['provider'] ) ? sanitize_key( (string) $properties['provider'] ) : 'unknown';
			$properties['retry_attempt'] = isset( $properties['retry_attempt'] ) ? max( 0, (int) $properties['retry_attempt'] ) : ( isset( $properties['retry_count'] ) ? max( 0, (int) $properties['retry_count'] ) : 0 );
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
	private static function normalize_generation_failure_event( array &$properties ): string {
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

		$properties['error_code'] = 'internal_unknown_error';
		return 'generation_failed';
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

	/**
	 * Telemetry install id: the hashed id from bbai_get_site_install_id(), never the site identifier.
	 */
	private static function resolve_site_id(): string {
		self::load_site_id_helpers();
		if ( function_exists( __NAMESPACE__ . '\\bbai_get_site_install_id' ) ) {
			return (string) bbai_get_site_install_id();
		}
		return '';
	}

	/**
	 * Existing site_hash join key: sha256 of the site identifier (unchanged contract).
	 */
	private static function resolve_site_hash(): string {
		$raw = self::resolve_raw_site_identifier();
		return '' === $raw ? '' : hash( 'sha256', $raw );
	}

	private static function resolve_raw_site_identifier(): string {
		self::load_site_id_helpers();
		return function_exists( __NAMESPACE__ . '\\get_site_identifier' ) ? (string) get_site_identifier() : '';
	}

	private static function load_site_id_helpers(): void {
		if ( ! function_exists( __NAMESPACE__ . '\\bbai_get_site_install_id' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
			$file = BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Replace the site identifier with the install id in any string, at any depth,
	 * so a stale queue entry or client payload can never carry it to PostHog.
	 *
	 * @param mixed $value Value to scrub.
	 * @return mixed
	 */
	public static function scrub_raw_site_identifier( $value ) {
		$raw = self::resolve_raw_site_identifier();
		if ( '' === $raw ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return false === strpos( $value, $raw ) ? $value : str_replace( $raw, self::resolve_site_id(), $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::scrub_raw_site_identifier( $item );
			}
		}
		return $value;
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
