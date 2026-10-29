<?php
/**
 * Site-identifying keys are removed at any depth (including $set / $set_once
 * and $-prefixed PostHog keys), and the strip runs even when no install id
 * can be resolved.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class TelemetrySiteIdentifierStripTest extends TestCase {
		const HOST = 'private.example.org';

		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			}
			$GLOBALS['bbai_test_remote_posts'] = array();
			unset( $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_OPTION ], $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_SOURCE_OPTION ] );
		}

		/** Bridge-style payload with identifiers at the top level, nested, and under $set. */
		private function crafted(): array {
			return array(
				'referrer'      => 'https://' . self::HOST . '/wp-admin/',
				'page_url'      => 'https://' . self::HOST . '/wp-admin/upload.php',
				'current_url'   => 'https://' . self::HOST . '/wp-admin/',
				'$current_url'  => 'https://' . self::HOST . '/wp-admin/',
				'$referrer'     => 'https://' . self::HOST . '/',
				'url'           => 'https://' . self::HOST . '/',
				'site_url'      => 'https://' . self::HOST . '/',
				'host'          => self::HOST,
				'$set'          => array(
					'website'  => 'https://' . self::HOST . '/',
					'site_url' => 'https://' . self::HOST . '/',
					'plan'     => 'free',
				),
				'$set_once'     => array(
					'initial_referrer' => 'https://' . self::HOST . '/',
					'URL'              => 'https://' . self::HOST . '/',
				),
				'context'       => array(
					'page_url' => 'https://' . self::HOST . '/x',
					'host'     => self::HOST,
					'tab'      => 'library',
				),
				'source_page'   => 'library',
				'success'       => true,
			);
		}

		private function assert_clean( array $props ): void {
			$this->assertStringNotContainsString( self::HOST, (string) json_encode( $props ) );
			$walk = function ( array $a ) use ( &$walk ) {
				foreach ( $a as $k => $v ) {
					if ( is_string( $k ) ) {
						$this->assertNotContains( strtolower( ltrim( $k, '$' ) ), BBAI_Telemetry::SITE_IDENTIFIER_KEYS, "key {$k} must be stripped" );
					}
					if ( is_array( $v ) ) {
						$walk( $v );
					}
				}
			};
			$walk( $props );
		}

		public function test_strip_recurses_into_nested_arrays_and_set(): void {
			$out = BBAI_Telemetry::strip_site_identifiers(
				array(
					'a'    => array( 'b' => array( 'c' => array( 'referrer' => 'x', 'keep' => 1 ) ) ),
					'$set' => array( 'website' => 'x', 'plan' => 'free' ),
					'Host' => 'x',
					'urls_checked' => 3,
				)
			);
			$this->assertSame(
				array(
					'a'    => array( 'b' => array( 'c' => array( 'keep' => 1 ) ) ),
					'$set' => array( 'plan' => 'free' ),
					'urls_checked' => 3,
				),
				$out
			);
		}

		public function test_crafted_bridge_payload_reaches_posthog_without_identifiers(): void {
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::capture_posthog_event( 'alt_generated', 'test_site_install_id_0123456789ab', $this->crafted() );

			$found = 0;
			foreach ( $GLOBALS['bbai_test_remote_posts'] as $call ) {
				$event = json_decode( (string) $call['args']['body'], true );
				// Every payload (the opt-in snapshot included) is clean.
				$this->assert_clean( $event['properties'] );
				if ( 'alt_generated' !== $event['event'] ) {
					continue;
				}
				++$found;
				$this->assertSame( 'library', $event['properties']['source_page'] );
				$this->assertSame( 'free', $event['properties']['$set']['plan'] ?? null, 'non-identifying $set keys survive' );
				$this->assertSame( 'library', $event['properties']['context']['tab'] ?? null );
			}
			$this->assertSame( 1, $found );
		}

		public function test_enrich_properties_strips_nested_identifiers(): void {
			$props = BBAI_Telemetry::enrich_properties( $this->crafted() );
			$this->assert_clean( $props );
			$this->assertSame( '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e', $props['site_install_id'] );
		}

		public function test_strip_runs_when_no_install_id_resolves(): void {
			// A stored id that sanitizes to '' makes with_site_identity() take its early return.
			$blank = str_repeat( ' ', 20 );
			$GLOBALS['bbai_test_options']['beepbeepai_site_id'] = $blank;
			if ( function_exists( 'bbai_get_site_identifier_option_key' ) ) {
				$GLOBALS['bbai_test_options'][ bbai_get_site_identifier_option_key() ] = $blank;
			}
			$m = new \ReflectionMethod( BBAI_Telemetry::class, 'with_site_identity' );
			$m->setAccessible( true );
			$props = $m->invoke( null, $this->crafted(), '' );

			$this->assertArrayNotHasKey( 'site_install_id', $props, 'early-return path was taken' );
			$this->assert_clean( $props );
			$this->assertSame( 'library', $props['source_page'] );
		}
	}
}
