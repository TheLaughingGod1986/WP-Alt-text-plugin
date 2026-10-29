<?php
/**
 * The client-event bridge (Core::forward_client_telemetry_to_posthog) picks its
 * PostHog distinct_id from account_id, user_id, license_id, site_install_id,
 * site_id or site_hash. Whichever key carries the site identifier, the event
 * goes out with the install id and the identifier is never sent.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class TelemetryBridgeDistinctIdTest extends TestCase {
		/** Site identifier set by tests/phpunit/bootstrap.php. */
		private const RAW = 'test_site_install_id_0123456789ab';
		/** sha256( 'oppti-install-v1:' . RAW ), hardcoded. */
		private const INSTALL_ID = '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e';

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

		public function test_client_bridge_distinct_id_never_uses_the_site_identifier(): void {
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
			// Each identity key the distinct_id loop reads, carrying the site identifier
			// (as a page context printed by an older build would).
			foreach ( array( 'account_id', 'user_id', 'license_id', 'site_install_id', 'site_id', 'site_hash' ) as $key ) {
				$m->invoke( $core, 'settings_saved', array( $key => self::RAW, 'source_page' => 'settings' ) );
			}

			$this->assertCount( 6, $this->bodies() );
			foreach ( $this->bodies() as $body ) {
				$this->assertStringNotContainsString( self::RAW, $body );
				$event = json_decode( $body, true );
				$this->assertSame( 'settings_saved', $event['event'] );
				$this->assertSame( self::INSTALL_ID, $event['distinct_id'] );
			}
		}

	}
}
