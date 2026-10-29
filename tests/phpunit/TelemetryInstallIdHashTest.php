<?php
/**
 * The telemetry install id is a one-way, versioned hash of the site
 * identifier. The site identifier itself never reaches a PostHog payload or
 * the analytics context printed into admin pages. The existing site_hash join
 * key (sha256 of the identifier) and the X-Telemetry-Install-ID header to the
 * OpptiAI API (the identifier, hashed by the backend) are unchanged.
 */
declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator {
	if ( ! class_exists( __NAMESPACE__ . '\\Usage_Tracker', false ) ) {
		class Usage_Tracker {
			const CACHE_KEY = 'bbai_usage_cache';
			public static function get_stats_display( $force = false ) { return array( 'plan' => 'free' ); }
			public static function get_upgrade_url() { return 'https://example.com/upgrade'; }
			public static function get_billing_portal_url() { return ''; }
		}
	}
}

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class TelemetryInstallIdHashTest extends TestCase {
		/** Site identifier set by tests/phpunit/bootstrap.php. */
		private const RAW = 'test_site_install_id_0123456789ab';
		/** sha256( 'oppti-install-v1:' . RAW ), hardcoded. */
		private const INSTALL_ID = '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e';
		/** sha256( RAW ): the existing site_hash value, unchanged. */
		private const SITE_HASH = '26eb845f570c26f6823ec19bc14d0c28b002e26c76d905c7b346b6aa64901840';

		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			}
			$GLOBALS['bbai_test_remote_posts'] = array();
			unset( $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_OPTION ], $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_SOURCE_OPTION ] );
		}

		private function bodies(): array {
			return array_map(
				static function ( $call ) {
					return (string) $call['args']['body'];
				},
				$GLOBALS['bbai_test_remote_posts']
			);
		}

		public function test_install_id_formula_matches_fixture(): void {
			// Cross-check fixture for the backend helper: sha256( 'oppti-install-v1:abc' ).
			$this->assertSame( 'ea66c63fec4b39dbfd491e385da2298bb0ad9025189b4de4a6c1944918e0aadb', \BeepBeepAI\AltTextGenerator\bbai_get_site_install_id( 'abc' ) );
			$this->assertSame( self::INSTALL_ID, \BeepBeepAI\AltTextGenerator\bbai_get_site_install_id() );
			$this->assertSame( '', \BeepBeepAI\AltTextGenerator\bbai_get_site_install_id( '' ) );
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', \BeepBeepAI\AltTextGenerator\bbai_get_site_install_id() );
		}

		public function test_server_capture_never_carries_the_site_identifier(): void {
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::capture_posthog_event(
				'alt_generated',
				self::RAW,
				array(
					'site_install_id' => self::RAW,
					'site_id'         => self::RAW,
					'journey_id'      => self::RAW,
					'context'         => array( 'note' => 'install ' . self::RAW ),
					'success'         => true,
				)
			);

			$found = 0;
			$this->assertNotEmpty( $this->bodies() );
			foreach ( $this->bodies() as $body ) {
				$this->assertStringNotContainsString( self::RAW, $body );
				$event = json_decode( $body, true );
				if ( 'alt_generated' !== $event['event'] ) {
					continue;
				}
				++$found;
				$this->assertSame( self::INSTALL_ID, $event['distinct_id'] );
				$this->assertSame( self::INSTALL_ID, $event['properties']['site_install_id'] );
				$this->assertSame( self::INSTALL_ID, $event['properties']['site_id'] );
				$this->assertSame( self::INSTALL_ID, $event['properties']['journey_id'] );
				$this->assertSame( self::SITE_HASH, $event['properties']['site_hash'] );
			}
			$this->assertSame( 1, $found );
		}

		public function test_defaults_and_lifecycle_use_the_install_id(): void {
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::capture_posthog_event( 'settings_saved', self::INSTALL_ID, array( 'source_page' => 'settings' ) );
			// A queue entry left by an older build, still carrying the identifier.
			update_option(
				BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION,
				array(
					array(
						'event'              => 'plugin_updated',
						'distinct_id'        => self::RAW,
						'properties'         => array( 'site_install_id' => self::RAW, 'journey_id' => self::RAW, '$insert_id' => 'bbai_lifecycle:' . self::RAW ),
						'occurred_at'        => '2026-10-29T10:00:00+00:00',
						'consented_at_raise' => true,
					),
				)
			);
			BBAI_Telemetry::flush_queued_lifecycle_events();

			$events = array();
			foreach ( $this->bodies() as $body ) {
				$this->assertStringNotContainsString( self::RAW, $body );
				$event                    = json_decode( $body, true );
				$events[ $event['event'] ] = $event;
			}
			$this->assertArrayHasKey( 'plugin_installed', $events, 'opt-in snapshot sent' );
			$this->assertSame( self::INSTALL_ID, $events['plugin_installed']['distinct_id'] );
			$this->assertSame(
				'bbai_opt_in_lifecycle:' . hash( 'sha256', 'plugin_installed|' . self::INSTALL_ID ),
				$events['plugin_installed']['properties']['$insert_id']
			);
			$this->assertSame( self::INSTALL_ID, $events['settings_saved']['properties']['site_install_id'] );
			$this->assertSame( self::INSTALL_ID, $events['settings_saved']['properties']['journey_id'] );
			$this->assertSame( self::SITE_HASH, $events['settings_saved']['properties']['site_hash'] );
			$this->assertSame( self::INSTALL_ID, $events['plugin_updated']['distinct_id'] );
		}

		public function test_telemetry_header_and_enriched_properties(): void {
			BBAI_Telemetry::set_consent( true );
			$headers = BBAI_Telemetry::request_telemetry_headers();
			// Sent only to the OpptiAI API, like X-Site-Key: stays the site identifier (the backend hashes it once).
			$this->assertSame( self::RAW, $headers['X-Telemetry-Install-ID'] );

			$props = BBAI_Telemetry::enrich_properties( array( 'source_page' => 'library' ) );
			$this->assertStringNotContainsString( self::RAW, (string) json_encode( $props ) );
			$this->assertSame( self::INSTALL_ID, $props['site_install_id'] );
			$this->assertSame( self::SITE_HASH, $props['site_hash'] );
		}

		public function test_admin_page_analytics_context_never_carries_the_site_identifier(): void {
			require_once __DIR__ . '/stubs-admin-assets.php';
			if ( ! function_exists( 'untrailingslashit' ) ) {
				function untrailingslashit( $v ) { return rtrim( (string) $v, '/\\' ); }
			}
			if ( ! function_exists( 'wp_create_nonce' ) ) {
				function wp_create_nonce( $a = '' ) { return 'nonce'; }
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-assets.php';
			require_once __DIR__ . '/fixtures-core-assets-host.php';
			BBAI_Telemetry::set_consent( true );

			$host = new \BBAI_Test_Core_Assets_Host();
			foreach ( array( 'get_posthog_client_config', 'get_telemetry_client_config' ) as $method ) {
				$m = new \ReflectionMethod( $host, $method );
				$m->setAccessible( true );
				$config = $m->invoke( $host );
				$this->assertStringNotContainsString( self::RAW, (string) json_encode( $config ), $method );
				$this->assertSame( self::INSTALL_ID, $config['context']['site_install_id'], $method );
				$this->assertSame( self::SITE_HASH, $config['context']['site_hash'], $method );
			}
		}
	}
}
