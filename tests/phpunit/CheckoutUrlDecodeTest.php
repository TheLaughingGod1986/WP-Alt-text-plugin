<?php
/**
 * Stripe Checkout URLs carry percent-encoded octets in the #fragment. The API
 * client's JSON decode must keep them (sanitize_text_field strips %XX, which
 * made Stripe show "This link is incomplete").
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CheckoutUrlDecodeTest extends TestCase {
	private const URL = 'https://checkout.stripe.com/c/pay/cs_live_a1Test#fidkdWxOYHwnPyd1blpxYHZxWjA0YCc%2FJ3dq%3D';

	protected function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'sanitize_key' ) ) {
			function sanitize_key( $value ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
			}
		}
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			// Mirrors WordPress: percent-encoded octets are removed.
			function sanitize_text_field( $value ) {
				$value = preg_replace( '/%[a-f0-9]{2}/i', '', (string) $value );
				return trim( preg_replace( '/[\r\n\t ]+/', ' ', $value ) );
			}
		}
		if ( ! function_exists( 'esc_url_raw' ) ) {
			function esc_url_raw( $url ) {
				return (string) $url;
			}
		}
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
	}

	private function client_url_map(): array {
		// Same map as API_Client_V2::request() uses.
		$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php' );
		$this->assertStringContainsString( "'url'         => 'esc_url_raw'", $src );
		$this->assertStringContainsString( "bbai_json_decode_array( \$body, \$url_keys )", $src );
		return array(
			'url'          => 'esc_url_raw',
			'checkoutUrl'  => 'esc_url_raw',
			'checkout_url' => 'esc_url_raw',
			'portalUrl'    => 'esc_url_raw',
			'portal_url'   => 'esc_url_raw',
		);
	}

	public function test_default_decode_strips_octets_reproducing_the_bug(): void {
		$out = bbai_json_decode_array( (string) json_encode( array( 'url' => self::URL ) ) );
		$this->assertNotSame( self::URL, $out['url'] );
	}

	public function test_client_map_keeps_checkout_url_intact_top_level_and_nested(): void {
		$map  = $this->client_url_map();
		$body = (string) json_encode( array( 'url' => self::URL, 'data' => array( 'url' => self::URL, 'sessionId' => 'cs_live_a1Test' ) ) );
		$out  = bbai_json_decode_array( $body, $map );
		$this->assertSame( self::URL, $out['url'] );
		$this->assertSame( self::URL, $out['data']['url'] );
	}
}
