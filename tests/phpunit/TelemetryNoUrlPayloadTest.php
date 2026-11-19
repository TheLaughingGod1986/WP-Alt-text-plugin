<?php
/**
 * No PostHog payload carries the site address, a host, a page path or any other
 * URL-shaped value, on every server-side path (direct capture, client bridge
 * events, the lifecycle queue and the opt-in snapshot). site_hash and
 * plugin_slug stay, and every event carries an is_test boolean.
 *
 * wp_remote_post is recorded, never sent. Run offline: unshare -rn vendor/bin/phpunit
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class TelemetryNoUrlPayloadTest extends TestCase {
		const HOST = 'leaky-shop.example.org';

		/** Keys that must never appear in a payload, at any depth. */
		const FORBIDDEN_KEYS = array( 'site_url', 'host', 'site_host', 'domain', 'home_url', '$current_url', '$host', '$pathname', '$referrer', '$referring_domain', 'current_url', 'pathname', 'referrer', 'referring_domain', 'url', 'landing_page', 'hostname' );

		/** URL, host, IP or path shaped value. Independent of the plugin's own matcher. */
		const URL_VALUE = '#([a-z][a-z0-9+.-]*://|^//|^/\S*$|\bwww\.|localhost|\b[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}\b|::1|\[::)#i';
		const IPV4_VALUE = '/\b\d{1,3}(\.\d{1,3}){3}\b/';

		protected function setUp(): void {
			parent::setUp();
			if ( ! function_exists( 'home_url' ) ) {
				// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
				function home_url( $path = '' ) {
					return ( $GLOBALS['bbai_t_home'] ?? 'https://' . TelemetryNoUrlPayloadTest::HOST ) . $path;
				}
			}
			if ( ! function_exists( 'wp_get_environment_type' ) ) {
				function wp_get_environment_type() {
					return $GLOBALS['bbai_t_env'] ?? 'production';
				}
			}
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			}
			$GLOBALS['bbai_test_remote_posts'] = array();
		}

		/** Every client event the server bridge forwards, read from the core allowlist, plus server-side events. */
		private function event_names(): array {
			$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php' );
			$start = strpos( $src, 'function is_posthog_forwarded_client_event' );
			$this->assertNotFalse( $start );
			$block = substr( $src, $start, strpos( $src, 'return ! empty', $start ) - $start );
			preg_match_all( "/'([a-z0-9_]+)'/", $block, $m );
			$names = array_merge(
				$m[1],
				array( 'alt_generated', 'generation_started', 'generation_completed', 'generation_failed', 'batch_generation_completed', 'signup_succeeded', 'login_succeeded', 'checkout_started', 'upgrade_clicked', 'user_activated', 'plugin_installed', 'plugin_activated', 'plugin_updated', 'plugin_deactivated' )
			);
			$names = array_values( array_unique( $names ) );
			$this->assertGreaterThan( 40, count( $names ), 'allowlist was parsed' );
			return $names;
		}

		/** What a page context or an old queue entry could carry. */
		private function hostile(): array {
			$url = 'https://' . self::HOST . '/wp-admin/upload.php?page=bbai';
			return array(
				'site_url'          => 'https://' . self::HOST . '/',
				'host'              => self::HOST,
				'site_host'         => self::HOST,
				'domain'            => self::HOST,
				'home_url'          => 'https://' . self::HOST,
				'$current_url'      => $url,
				'$host'             => self::HOST,
				'$pathname'         => '/wp-admin/upload.php',
				'$referrer'         => $url,
				'$referring_domain' => self::HOST,
				'landing_page'      => '/wp-admin/admin.php',
				'checkout_url'      => 'https://checkout.example.net/pay',
				'return_to'         => $url,
				'note'              => 'see ' . self::HOST,
				'server_ip'         => '203.0.113.7',
				'ip6'               => '2001:db8::1',
				'contact'           => 'owner@' . self::HOST,
				'$set'              => array( 'website' => $url, 'plan' => 'free', 'company' => self::HOST ),
				'$set_once'         => array( '$initial_current_url' => $url, '$initial_referring_domain' => self::HOST ),
				'context'           => array( 'tab' => 'library', 'page_url' => $url, 'origin' => 'https://' . self::HOST ),
				'source_page'       => 'library',
				'plan'              => 'free',
				'count'             => 3,
			);
		}

		private function assert_clean_payload( array $event ): void {
			$json = (string) json_encode( $event );
			$this->assertStringNotContainsString( self::HOST, $json );
			$this->assertStringNotContainsString( '203.0.113.7', $json );
			$this->assertStringNotContainsString( 'example.', $json );
			$this->assertDoesNotMatchRegularExpression( self::URL_VALUE, (string) $event['distinct_id'] );
			$props      = $event['properties'];
			$violations = array();
			$walk       = function ( array $a, string $path ) use ( &$walk, &$violations ) {
				foreach ( $a as $k => $v ) {
					if ( is_string( $k ) && ( in_array( strtolower( $k ), self::FORBIDDEN_KEYS, true ) || in_array( '$' . strtolower( ltrim( $k, '$' ) ), self::FORBIDDEN_KEYS, true ) ) ) {
						$violations[] = "key {$path}{$k}";
					}
					if ( is_array( $v ) ) {
						$walk( $v, $path . $k . '.' );
						continue;
					}
					if ( is_string( $v ) ) {
						if ( preg_match( self::URL_VALUE, $v ) ) {
							$violations[] = "URL-shaped value at {$path}{$k}";
						}
						if ( ! ( is_string( $k ) && str_ends_with( $k, 'version' ) ) && preg_match( self::IPV4_VALUE, $v ) ) {
							$violations[] = "IP value at {$path}{$k}";
						}
					}
				}
			};
			$walk( $props, '' );
			$this->assertSame( array(), $violations, (string) ( $event['event'] ?? '' ) );
			$this->assertNotEmpty( $props['site_hash'] ?? '', 'site_hash is kept' );
			$this->assertSame( 'beepbeep-ai-alt-text-generator', $props['plugin_slug'] ?? '', 'plugin_slug is kept' );
			$this->assertIsBool( $props['is_test'] ?? null, 'is_test is a boolean' );
			if ( isset( $props['$set'] ) ) {
				$this->assertIsBool( $props['$set']['is_test'] ?? null, 'person set carries is_test' );
			}
		}

		private function sent(): array {
			$out = array();
			foreach ( $GLOBALS['bbai_test_remote_posts'] as $call ) {
				$out[] = json_decode( (string) $call['args']['body'], true );
			}
			return $out;
		}

		public function test_every_event_payload_is_free_of_urls_and_hosts(): void {
			BBAI_Telemetry::set_consent( true );
			$names = $this->event_names();
			foreach ( $names as $name ) {
				BBAI_Telemetry::capture_posthog_event( $name, 'test_site_install_id_0123456789ab', $this->hostile() );
			}
			$sent = $this->sent();
			$this->assertGreaterThanOrEqual( count( $names ), count( $sent ) );
			foreach ( $sent as $event ) {
				$this->assert_clean_payload( $event );
			}
			$any = $sent[ count( $sent ) - 1 ]['properties'];
			$this->assertSame( 'library', $any['source_page'] );
			$this->assertSame( 'library', $any['context']['tab'] ?? null, 'non-URL nested values survive' );
			$this->assertSame( 'free', $any['$set']['plan'] ?? null, 'non-URL person values survive' );
		}

		public function test_lifecycle_queue_and_opt_in_snapshot_are_clean(): void {
			$GLOBALS['bbai_test_options'][ BBAI_Telemetry::INSTALLED_MARKER_OPTION ]  = gmdate( 'c', time() - 600 );
			$GLOBALS['bbai_test_options'][ BBAI_Telemetry::ACTIVATION_MARKER_OPTION ] = gmdate( 'c', time() - 590 );
			BBAI_Telemetry::set_consent( true );
			BBAI_Telemetry::record_opt_in_lifecycle();
			BBAI_Telemetry::queue_lifecycle_event( 'plugin_updated', $this->hostile() );
			// A stale queue entry written by an older build, with the host inside.
			$GLOBALS['bbai_test_options'][ BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION ] = array(
				array(
					'event'              => 'plugin_activated',
					'distinct_id'        => 'test_site_install_id_0123456789ab',
					'properties'         => $this->hostile(),
					'occurred_at'        => gmdate( 'c' ),
					'consented_at_raise' => true,
				),
			);
			BBAI_Telemetry::flush_queued_lifecycle_events();
			$sent = $this->sent();
			$this->assertGreaterThanOrEqual( 3, count( $sent ) );
			foreach ( $sent as $event ) {
				$this->assert_clean_payload( $event );
			}
		}

		public function test_enrich_properties_is_clean(): void {
			$props = BBAI_Telemetry::enrich_properties( $this->hostile() );
			$this->assert_clean_payload( array( 'distinct_id' => 'x', 'properties' => $props ) );
		}

		public function test_url_shape_matcher(): void {
			foreach ( array( 'https://a.b/c', '//cdn.x.io/a.js', '/wp-admin/upload.php', 'www.foo', 'shop.example.co.uk', 'user@mail.example.com', '127.0.0.1', '10.0.0.12', '::1', '2001:db8::1', 'fe80:0:0:0:0:0:0:1', 'http://localhost:8080' ) as $v ) {
				$this->assertTrue( BBAI_Telemetry::is_url_shaped( $v ), $v );
			}
			foreach ( array( 'free', 'alt_text', 'library', '4.6.145', '6.8.3', 'opptiai_usage_v2', 'generation_failed', 'e.g', '2026-11-19T16:17:00+00:00', '16:17:00', 'beepbeep-ai-alt-text-generator', '' ) as $v ) {
				$this->assertFalse( BBAI_Telemetry::is_url_shaped( $v ), $v );
			}
			$this->assertFalse( BBAI_Telemetry::is_url_shaped( '4.6.142.1', true ), 'dotted version' );
		}
	}
}
