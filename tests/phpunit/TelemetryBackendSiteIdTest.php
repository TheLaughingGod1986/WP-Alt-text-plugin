<?php
/**
 * Sign-in, sign-up, licence and usage responses can carry site_id, site_hash
 * or a site object. None of those values may reach PostHog: the analytics
 * identity is always this site's own install id and site hash.
 *
 * FAKE stands in for a credential an API response might return.
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
	final class TelemetryBackendSiteIdTest extends TestCase {
		/** Not a real key. */
		private const FAKE = 'fakesitekey_0009_d41d8cd98f00b204e9800998ecf8427e';
		/** sha256( 'oppti-install-v1:' . identifier from bootstrap.php ), hardcoded. */
		private const INSTALL_ID = '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e';
		/** sha256( identifier from bootstrap.php ): the local site_hash. */
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

		/** The user and site blocks a sign-in / licence response would leave in storage. */
		private static function poisoned_api_client() {
			$fake = self::FAKE;
			return new class( $fake ) {
				private $fake;
				public function __construct( $fake ) { $this->fake = $fake; }
				public function get_api_url() { return 'https://api.example.com/'; }
				public function get_token() { return 'token'; }
				public function get_license_key() { return ''; }
				public function has_active_license() { return false; }
				public function is_authenticated() { return true; }
				public function get_user_data() {
					return array( 'id' => '', 'site_id' => $this->fake, 'site_hash' => $this->fake, 'site' => array( 'id' => $this->fake, 'site_hash' => $this->fake ) );
				}
				public function get_license_data() {
					return array(
						'organization' => array( 'plan' => 'free' ),
						'site'         => array( 'id' => $this->fake, '_id' => $this->fake, 'site_hash' => $this->fake ),
					);
				}
			};
		}

		public function test_signin_response_site_ids_never_reach_page_analytics_context(): void {
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

			$host             = new \BBAI_Test_Core_Assets_Host();
			$host->api_client = self::poisoned_api_client();

			$m = new \ReflectionMethod( $host, 'get_posthog_identity_context' );
			$m->setAccessible( true );
			$identity = $m->invoke( $host, $host->api_client->get_user_data(), $host->api_client->get_license_data(), self::SITE_HASH, 'free' );
			$this->assertSame( self::INSTALL_ID, $identity['site_id'] );
			$this->assertSame( self::INSTALL_ID, $identity['site_install_id'] );

			foreach ( array( 'get_posthog_client_config', 'get_telemetry_client_config' ) as $method ) {
				$m = new \ReflectionMethod( $host, $method );
				$m->setAccessible( true );
				$config = $m->invoke( $host );
				$this->assertStringNotContainsString( self::FAKE, (string) json_encode( $config ), $method );
				$this->assertSame( self::INSTALL_ID, $config['context']['site_install_id'], $method );
				$this->assertSame( self::INSTALL_ID, $config['context']['site_id'], $method );
				$this->assertSame( self::SITE_HASH, $config['context']['site_hash'], $method );
			}
		}

		public function test_server_capture_replaces_supplied_site_ids_with_local_ones(): void {
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::capture_posthog_event(
				'login_succeeded',
				self::INSTALL_ID,
				array(
					'site_id'         => self::FAKE,
					'site_hash'       => self::FAKE,
					'site_install_id' => self::FAKE,
					'siteHash'        => self::FAKE,
					'site'            => array( 'id' => self::FAKE, 'site_hash' => self::FAKE ),
					'$set'            => array( 'site_id' => self::FAKE, 'site_hash' => self::FAKE, 'plan' => 'free' ),
					'$set_once'       => array( 'site_install_id' => self::FAKE ),
				)
			);

			$event = null;
			$this->assertNotEmpty( $this->bodies() );
			foreach ( $this->bodies() as $body ) {
				$this->assertStringNotContainsString( self::FAKE, $body );
				$decoded = json_decode( $body, true );
				if ( 'login_succeeded' === $decoded['event'] ) {
					$event = $decoded;
				}
			}
			$this->assertNotNull( $event );
			$this->assertSame( self::INSTALL_ID, $event['properties']['site_id'] );
			$this->assertSame( self::INSTALL_ID, $event['properties']['site_install_id'] );
			$this->assertSame( self::SITE_HASH, $event['properties']['site_hash'] );
			if ( isset( $event['properties']['$set']['site_id'] ) ) {
				$this->assertSame( self::INSTALL_ID, $event['properties']['$set']['site_id'] );
			}
		}
	}
}
