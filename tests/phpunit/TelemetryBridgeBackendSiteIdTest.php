<?php
/**
 * The client-event bridge (Core::forward_client_telemetry_to_posthog) must not
 * use a browser-supplied site_install_id, site_id or site_hash (which may have
 * come from an API response) as the PostHog distinct_id or as event
 * properties. It uses this site's own install id and site hash.
 *
 * FAKE stands in for a credential an API response might return.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class TelemetryBridgeBackendSiteIdTest extends TestCase {
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

		public function test_client_bridge_ignores_supplied_site_ids_for_distinct_id_and_props(): void {
			require __DIR__ . '/stubs-process-queue.php';
			if ( ! function_exists( 'date_i18n' ) ) {
				function date_i18n( $format, $ts = false ) { return gmdate( $format, $ts ? (int) $ts : time() ); }
			}
			if ( ! function_exists( 'wp_date' ) ) {
				function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?? time() ); }
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
			BBAI_Telemetry::set_consent( true );
			$GLOBALS['bbai_test_remote_posts'] = array();

			$ref  = new \ReflectionClass( \BeepBeepAI\AltTextGenerator\Core::class );
			$core = $ref->newInstanceWithoutConstructor();
			$m    = new \ReflectionMethod( $core, 'forward_client_telemetry_to_posthog' );
			$m->setAccessible( true );
			foreach ( array( 'site_install_id', 'site_id', 'site_hash' ) as $key ) {
				$m->invoke( $core, 'settings_saved', array( $key => self::FAKE, 'source_page' => 'settings' ) );
			}
			$m->invoke( $core, 'settings_saved', array( 'site_id' => self::FAKE, 'site_hash' => self::FAKE, 'site_install_id' => self::FAKE ) );

			$this->assertCount( 4, $this->bodies() );
			foreach ( $this->bodies() as $body ) {
				$this->assertStringNotContainsString( self::FAKE, $body );
				$event = json_decode( $body, true );
				$this->assertSame( self::INSTALL_ID, $event['distinct_id'] );
				$this->assertSame( self::INSTALL_ID, $event['properties']['site_id'] );
				$this->assertSame( self::SITE_HASH, $event['properties']['site_hash'] );
			}
		}
	}
}
