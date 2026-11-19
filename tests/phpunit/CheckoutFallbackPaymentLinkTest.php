<?php
/**
 * checkout_fallback_payment_link: forwarded by the server bridge (opt-in only)
 * with plugin, source, reason and plan and no site address, URL or email, and a
 * Payment Link fallback URL that carries client_reference_id plus the utm_*.
 *
 * wp_remote_post is recorded, never sent. Run offline: unshare -rn vendor/bin/phpunit
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class CheckoutFallbackPaymentLinkTest extends TestCase {
		const HOST = 'leaky-shop.example.org';
		/** sha256( 'oppti-install-v1:' . the bootstrap site identifier ). */
		const INSTALL_ID = '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e';
		const LINK = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';

		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			}
			unset( $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_OPTION ], $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_SOURCE_OPTION ] );
		}

		private function core() {
			require __DIR__ . '/stubs-process-queue.php';
			if ( ! function_exists( 'date_i18n' ) ) {
				function date_i18n( $format, $ts = false ) { return gmdate( $format, $ts ? (int) $ts : time() ); }
			}
			if ( ! function_exists( 'wp_date' ) ) {
				function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?? time() ); }
			}
			if ( ! function_exists( 'add_query_arg' ) ) {
				function add_query_arg( array $args, string $url ) {
					$parts = array();
					foreach ( $args as $k => $v ) {
						$parts[] = $k . '=' . $v;
					}
					return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $parts );
				}
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
			$ref = new \ReflectionClass( \BeepBeepAI\AltTextGenerator\Core::class );
			return $ref->newInstanceWithoutConstructor();
		}

		/** What the dashboard sends for one fallback, plus what must never get through. */
		private function fallback_props(): array {
			return array(
				'plugin'           => 'alt_text',
				'source'           => 'usage_banner',
				'source_page'      => 'dashboard',
				'trigger_location' => 'usage_banner',
				'reason'           => 'http_502',
				'plan'             => 'pro',
				'target_plan'      => 'pro',
				'site_url'         => 'https://' . self::HOST . '/',
				'host'             => self::HOST,
				'url'              => self::LINK . '?client_reference_id=alt_text_x',
				'fallback_url'     => self::LINK,
				'email'            => 'owner@' . self::HOST,
				'admin_email'      => 'admin@example.com',
				'note'             => 'from ' . self::HOST,
			);
		}

		public function test_event_is_on_the_bridge_allowlist(): void {
			$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php' );
			$start = strpos( $src, 'function is_posthog_forwarded_client_event' );
			$block = substr( $src, $start, strpos( $src, 'return ! empty', $start ) - $start );
			$this->assertStringContainsString( "'checkout_fallback_payment_link'", $block );
		}

		public function test_bridge_sends_it_once_with_plugin_source_reason_and_no_address_or_email(): void {
			$core = $this->core();
			BBAI_Telemetry::set_consent( true );
			$GLOBALS['bbai_test_remote_posts'] = array();

			$m = new \ReflectionMethod( $core, 'forward_client_telemetry_to_posthog' );
			$m->setAccessible( true );
			$m->invoke( $core, 'checkout_fallback_payment_link', $this->fallback_props() );

			$this->assertCount( 1, $GLOBALS['bbai_test_remote_posts'] );
			$body  = (string) $GLOBALS['bbai_test_remote_posts'][0]['args']['body'];
			$event = json_decode( $body, true );
			$this->assertSame( 'checkout_fallback_payment_link', $event['event'] );
			$p = $event['properties'];
			$this->assertSame( 'alt_text', $p['plugin'] );
			$this->assertSame( 'usage_banner', $p['source'] );
			$this->assertSame( 'http_502', $p['reason'] );
			// plan is the site's current plan (set server-side); the plan picked is target_plan.
			$this->assertSame( 'pro', $p['target_plan'] );
			$this->assertIsBool( $p['is_test'] );
			foreach ( array( 'site_url', 'host', 'url', 'fallback_url', 'email', 'admin_email' ) as $key ) {
				$this->assertArrayNotHasKey( $key, $p, $key );
			}
			$this->assertStringNotContainsString( self::HOST, $body );
			$this->assertStringNotContainsString( 'buy.stripe.com', $body );
			$this->assertStringNotContainsString( '@', $body );
		}

		public function test_nothing_is_sent_without_opt_in(): void {
			$core = $this->core();
			BBAI_Telemetry::set_consent( false );
			$GLOBALS['bbai_test_remote_posts'] = array();
			$m = new \ReflectionMethod( $core, 'forward_client_telemetry_to_posthog' );
			$m->setAccessible( true );
			$m->invoke( $core, 'checkout_fallback_payment_link', $this->fallback_props() );
			$this->assertCount( 0, $GLOBALS['bbai_test_remote_posts'] );
		}

		public function test_payment_link_gets_client_reference_id_and_utm(): void {
			$core = $this->core();
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-attribution.php';
			$GLOBALS['bbai_test_options'][ \BeepBeepAI\AltTextGenerator\BBAI_Attribution::OPTION_KEY ] = array(
				'utm_source'   => 'wporg',
				'utm_campaign' => 'first_touch',
			);

			$url = $core->decorate_payment_link_url( self::LINK, array( 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'trigger_location' => 'usage_banner' ) );
			$q   = array();
			parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
			$this->assertSame( 'buy.stripe.com', parse_url( $url, PHP_URL_HOST ) );
			$this->assertSame( 'alt_text_' . self::INSTALL_ID, $q['client_reference_id'] );
			$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{1,200}$/', $q['client_reference_id'] );
			$this->assertSame( 'newsletter', $q['utm_source'] );
			$this->assertSame( 'email', $q['utm_medium'] );
			$this->assertSame( 'first_touch', $q['utm_campaign'] );
			$this->assertArrayNotHasKey( 'trigger_location', $q );

			// Existing params are kept, never overridden.
			$kept = $core->decorate_payment_link_url( self::LINK . '?client_reference_id=keep_me', array() );
			$this->assertStringContainsString( 'client_reference_id=keep_me', $kept );
			$this->assertSame( 1, substr_count( $kept, 'client_reference_id=' ) );

			// Anything that is not a https://buy.stripe.com/ link is left alone.
			foreach ( array( 'https://checkout.stripe.com/c/pay/cs_test_1', 'http://buy.stripe.com/x', 'https://buy.stripe.com.evil.test/x', '' ) as $other ) {
				$this->assertSame( $other, $core->decorate_payment_link_url( $other, array( 'utm_source' => 'x' ) ) );
			}
		}

		public function test_checkout_error_response_uses_the_decorated_link_and_js_gets_the_params(): void {
			$core = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php' );
			$this->assertStringContainsString( '$fallback_checkout_url = $this->decorate_payment_link_url($this->get_checkout_fallback_url($plan_hint, $price_id), $this->get_checkout_request_attribution());', $core );
			$assets = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-assets.php' );
			$this->assertStringContainsString( "'payment_link_params'    => method_exists( \$this, 'get_payment_link_params' ) ? \$this->get_payment_link_params() : array(),", $assets );
		}
	}
}
