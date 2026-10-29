<?php
/**
 * Checkout attribution carries only the checkout context, the UTM tags, the
 * referrer host and the landing page path. Ids, keys and email never reach
 * the /billing/checkout request, even when the stored option or the click
 * context is crafted to include them.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\API_Client_V2;
use BeepBeepAI\AltTextGenerator\BBAI_Attribution;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CheckoutAttributionAllowlistTest extends TestCase {
	private const ALLOWED = array(
		'trigger_feature', 'trigger_location', 'source_page', 'target_plan', 'current_plan', 'source',
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer', 'landing_page',
	);

	private const FORBIDDEN = array(
		'account_id', 'user_id', 'license_key', 'license_key_present', 'site_id', 'site_hash', 'email',
		'acquisition_channel', 'captured_at',
	);

	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WP_Error' ) ) {
			eval( 'class WP_Error { public function __construct( private $code = "", private $message = "", private $data = null ) {} public function get_error_code() { return $this->code; } public function get_error_message() { return $this->message; } public function get_error_data() { return $this->data; } }' );
		}
		if ( ! function_exists( 'is_wp_error' ) ) {
			function is_wp_error( $value ) { return $value instanceof WP_Error; }
		}
		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = '' ) { return $text; }
		}
		if ( ! function_exists( 'absint' ) ) {
			function absint( $value ) { return abs( (int) $value ); }
		}
		if ( ! function_exists( 'sanitize_email' ) ) {
			function sanitize_email( $value ) { return $value; }
		}
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
		}
		if ( ! function_exists( 'sanitize_key' ) ) {
			function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ); }
		}
		if ( ! function_exists( 'trailingslashit' ) ) {
			function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
		}
		if ( ! function_exists( 'wp_parse_url' ) ) {
			function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
		}
		if ( ! function_exists( 'wp_json_encode' ) ) {
			function wp_json_encode( $value ) { return json_encode( $value ); }
		}
		if ( ! function_exists( 'get_bloginfo' ) ) {
			function get_bloginfo( $key ) { return '6.8'; }
		}
		if ( ! function_exists( 'wp_remote_request' ) ) {
			function wp_remote_request( $url, $args ) {
				$GLOBALS['bbai_test_http_requests'][] = array( 'url' => $url, 'args' => $args );
				return array( 'status' => 200, 'body' => array( 'success' => true, 'url' => 'https://checkout.stripe.com/c/pay/cs_test_a1' ) );
			}
		}
		if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
			function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
		}
		if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
			function wp_remote_retrieve_body( $response ) { return json_encode( $response['body'] ); }
		}
		if ( ! function_exists( 'esc_url_raw' ) ) {
			function esc_url_raw( $value ) { return $value; }
		}
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-attribution.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';

		$GLOBALS['bbai_test_http_requests']                          = array();
		$GLOBALS['bbai_test_options']['beepbeepai_site_fingerprint'] = 'test-fingerprint';
		$GLOBALS['bbai_test_options'][ BBAI_Attribution::OPTION_KEY ] = array(
			'utm_source'          => 'newsletter',
			'utm_medium'          => 'email',
			'utm_campaign'        => 'october',
			'utm_content'         => 'cta',
			'utm_term'            => 'alt text',
			'referrer'            => 'https://Someone@WWW.Google.com:443/search?q=alt+text&email=a%40example.test',
			'landing_page'        => 'https://example.com/wp-admin/admin.php?page=bbai&license_key=LK-SECRET#top',
			'acquisition_channel' => 'newsletter',
			'captured_at'         => '2026-10-29T10:00:00+00:00',
			'license_key'         => 'LK-STORED-SECRET',
			'email'               => 'stored@example.test',
		);
	}

	private function crafted_click_context(): array {
		return array(
			'trigger_feature'     => 'out_of_credits',
			'trigger_location'    => 'dashboard_banner',
			'source_page'         => 'dashboard',
			'target_plan'         => 'growth',
			'current_plan'        => 'free',
			'source'              => 'app',
			'account_id'          => 'acct-123',
			'user_id'             => 'user-456',
			'license_key'         => 'LK-CRAFTED-SECRET',
			'license_key_present' => '1',
			'site_id'             => 'site-789',
			'site_hash'           => 'hash-abc',
			'email'               => 'crafted@example.test',
		);
	}

	public function test_for_checkout_keeps_only_allowed_keys_host_and_path(): void {
		$out = BBAI_Attribution::for_checkout( array_merge( BBAI_Attribution::get_payload(), $this->crafted_click_context() ) );

		$this->assertSame( array(), array_values( array_diff( array_keys( $out ), self::ALLOWED ) ) );
		$this->assertSame( 'www.google.com', $out['referrer'] );
		$this->assertSame( '/wp-admin/admin.php', $out['landing_page'] );
		$this->assertSame( 'growth', $out['target_plan'] );
		$this->assertSame( 'alt text', $out['utm_term'] );
		foreach ( self::FORBIDDEN as $key ) {
			$this->assertArrayNotHasKey( $key, $out );
		}
	}

	public function test_referrer_and_landing_page_normalisers(): void {
		$this->assertSame( 'google.com', BBAI_Attribution::referrer_host( 'google.com/search?q=1' ) );
		$this->assertSame( 'news.example.org', BBAI_Attribution::referrer_host( '//news.example.org/a/b#c' ) );
		$this->assertSame( '', BBAI_Attribution::referrer_host( '' ) );
		$this->assertSame( '/wp-admin/admin.php', BBAI_Attribution::landing_path( '/wp-admin/admin.php?page=bbai#x' ) );
		$this->assertSame( '/landing/', BBAI_Attribution::landing_path( 'https://example.com/landing/?utm_source=x' ) );
		$this->assertSame( '', BBAI_Attribution::landing_path( '?only=query' ) );
	}

	public function test_checkout_request_body_never_carries_identity_or_full_urls(): void {
		$ref = new ReflectionClass( API_Client_V2::class );
		$api = $ref->newInstanceWithoutConstructor();
		$ref->getProperty( 'api_url' )->setValue( $api, 'https://example.test/api' );

		$api->create_checkout_session( 'price_test_1', 'https://example.com/ok', 'https://example.com/cancel', $this->crafted_click_context() );

		$this->assertNotEmpty( $GLOBALS['bbai_test_http_requests'] );
		$request = $GLOBALS['bbai_test_http_requests'][0];
		$this->assertStringEndsWith( '/billing/checkout', $request['url'] );
		$raw  = (string) $request['args']['body'];
		$body = json_decode( $raw, true );
		$this->assertIsArray( $body );

		// Checkout still gets what it needs to work.
		$this->assertSame( 'price_test_1', $body['priceId'] );
		$this->assertSame( 'https://example.com/ok', $body['successUrl'] );
		$this->assertSame( 'https://example.com/cancel', $body['cancelUrl'] );
		$this->assertSame( 'growth', $body['target_plan'] );
		$this->assertSame( 'out_of_credits', $body['trigger_feature'] );

		$this->assertSame( 'www.google.com', $body['referrer'] );
		$this->assertSame( '/wp-admin/admin.php', $body['landing_page'] );
		foreach ( self::FORBIDDEN as $key ) {
			$this->assertArrayNotHasKey( $key, $body );
		}
		foreach ( array( 'LK-CRAFTED-SECRET', 'LK-STORED-SECRET', 'LK-SECRET', 'crafted@example.test', 'stored@example.test', 'example.test', 'q=alt', 'page=bbai' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, str_replace( 'https://example.test/api', '', $raw ) );
		}
	}
}
