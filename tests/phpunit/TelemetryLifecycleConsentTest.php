<?php
/**
 * Lifecycle events raised before consent are discarded, never replayed as
 * consented; opt-in sends one honestly marked snapshot; no site URL or host
 * reaches PostHog.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class TelemetryLifecycleConsentTest extends TestCase {
		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			}
			$GLOBALS['bbai_test_remote_posts'] = array();
			foreach ( array(
				BBAI_Telemetry::CONSENT_OPTION,
				BBAI_Telemetry::CONSENT_SOURCE_OPTION,
				BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION,
				BBAI_Telemetry::INSTALLED_MARKER_OPTION,
				BBAI_Telemetry::LEGACY_INSTALLED_MARKER_OPTION,
				BBAI_Telemetry::ACTIVATION_MARKER_OPTION,
				BBAI_Telemetry::OPT_IN_LIFECYCLE_SENT_OPTION,
				BBAI_Telemetry::CURRENT_VERSION_OPTION,
				BBAI_Telemetry::LEGACY_VERSION_OPTION,
			) as $key ) {
				unset( $GLOBALS['bbai_test_options'][ $key ] );
			}
		}

		/** @return array<int,array<string,mixed>> */
		private function sent(): array {
			return array_map(
				static function ( $call ) {
					return json_decode( (string) $call['args']['body'], true );
				},
				$GLOBALS['bbai_test_remote_posts']
			);
		}

		public function test_activation_without_consent_stores_and_sends_nothing(): void {
			BBAI_Telemetry::record_activation_lifecycle();
			$this->assertSame( array(), get_option( BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION, array() ) );
			$this->assertSame( array(), $this->sent() );
			// Local markers are still recorded (they never leave the site on their own).
			$this->assertNotFalse( get_option( BBAI_Telemetry::INSTALLED_MARKER_OPTION, false ) );
		}

		public function test_pre_consent_events_are_not_replayed_after_opt_in(): void {
			BBAI_Telemetry::record_activation_lifecycle();
			// Simulate an entry left by an older build that queued without consent.
			update_option(
				BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION,
				array(
					array(
						'event'         => 'plugin_installed',
						'distinct_id'   => 'test_site_install_id_0123456789ab',
						'properties'    => array( 'site_url' => 'https://private.example.org/' ),
						'queued_at'     => '2026-10-29T10:57:56+00:00',
						'lifecycle_key' => 'legacy',
					),
				)
			);
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::flush_queued_lifecycle_events();

			$events = $this->sent();
			foreach ( $events as $event ) {
				$this->assertNotSame( 'queued_flush', $event['properties']['lifecycle_delivery'] ?? '' );
			}
			$this->assertSame( array(), get_option( BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION, array() ) );
		}

		public function test_opt_in_sends_one_marked_snapshot_with_true_times(): void {
			update_option( BBAI_Telemetry::INSTALLED_MARKER_OPTION, '2026-10-20T08:00:00+00:00' );
			update_option( BBAI_Telemetry::ACTIVATION_MARKER_OPTION, '2026-10-20T08:00:05+00:00' );
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::set_consent( true );

			$events = $this->sent();
			$this->assertCount( 2, $events );
			$by_name = array_column( $events, null, 'event' );
			$this->assertSame( 'opt_in_snapshot', $by_name['plugin_installed']['properties']['lifecycle_delivery'] );
			$this->assertTrue( $by_name['plugin_installed']['properties']['recorded_at_opt_in'] );
			$this->assertSame( '2026-10-20T08:00:00+00:00', $by_name['plugin_installed']['properties']['first_seen_at'] );
			$this->assertSame( '2026-10-20T08:00:05+00:00', $by_name['plugin_activated']['properties']['activated_at'] );
			// The event itself is timestamped at opt-in, not back-dated to install.
			$this->assertSame( $by_name['plugin_installed']['properties']['opted_in_at'], $by_name['plugin_installed']['timestamp'] );
			$this->assertTrue( $by_name['plugin_installed']['properties']['telemetry_consent'] );
		}

		public function test_consented_lifecycle_is_sent_with_its_occurred_at(): void {
			BBAI_Telemetry::set_consent( true );
			$GLOBALS['bbai_test_remote_posts'] = array();
			BBAI_Telemetry::queue_lifecycle_event( 'plugin_updated', array( 'previous_version' => '4.6.143' ) );
			$events = $this->sent();
			$this->assertCount( 1, $events );
			$this->assertSame( 'plugin_updated', $events[0]['event'] );
			$this->assertSame( $events[0]['properties']['occurred_at'], $events[0]['timestamp'] );
			$this->assertTrue( $events[0]['properties']['telemetry_consent'] );
		}

		public function test_queued_event_keeps_original_occurred_at_when_flushed_later(): void {
			BBAI_Telemetry::set_consent( true );
			$GLOBALS['bbai_test_remote_posts'] = array();
			update_option(
				BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION,
				array(
					array(
						'event'              => 'plugin_updated',
						'distinct_id'        => 'test_site_install_id_0123456789ab',
						'properties'         => array( 'previous_version' => '4.6.143' ),
						'occurred_at'        => '2026-10-28T09:00:00+00:00',
						'consented_at_raise' => true,
						'lifecycle_key'      => 'k1',
					),
				)
			);
			BBAI_Telemetry::flush_queued_lifecycle_events();
			$events = $this->sent();
			$this->assertCount( 1, $events );
			$this->assertSame( '2026-10-28T09:00:00+00:00', $events[0]['timestamp'] );
			$this->assertSame( '2026-10-28T09:00:00+00:00', $events[0]['properties']['occurred_at'] );
		}

		public function test_revocation_drops_queue(): void {
			BBAI_Telemetry::set_consent( true );
			update_option( BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION, array( array( 'event' => 'plugin_updated', 'consented_at_raise' => true ) ) );
			BBAI_Telemetry::set_consent( false );
			$this->assertSame( array(), get_option( BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION, array() ) );
		}

		public function test_no_site_url_or_host_in_any_payload(): void {
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::capture_posthog_event(
				'alt_generated',
				'test_site_install_id_0123456789ab',
				array(
					'site_url'  => 'https://private.example.org/',
					'host'      => 'private.example.org',
					'site_host' => 'private.example.org',
					'success'   => true,
				)
			);
			foreach ( $this->sent() as $event ) {
				foreach ( BBAI_Telemetry::SITE_IDENTIFIER_KEYS as $key ) {
					$this->assertArrayNotHasKey( $key, $event['properties'] );
				}
				$this->assertStringNotContainsString( 'private.example.org', wp_json_encode( $event ) );
				$this->assertStringNotContainsString( 'staging.oppti-qa.invalid', wp_json_encode( $event ) );
				$this->assertSame( 'opptiai_usage_v2', $event['properties']['telemetry_schema'] );
				$this->assertSame( '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e', $event['properties']['site_install_id'] );
			}
		}
	}
}
