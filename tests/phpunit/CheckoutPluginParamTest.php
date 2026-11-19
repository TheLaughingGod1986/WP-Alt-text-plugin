<?php
/**
 * The /billing/checkout request says which plugin it came from (plugin=alt_text)
 * next to the UTM tags it already forwards, never carries the referrer host or
 * landing path, keeps the UTM tags, hashed site_install_id and plugin on the
 * user-not-found retry, and an expired session no longer retries a
 * /billing/checkout/guest route the backend does not have.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\API_Client_V2;
use BeepBeepAI\AltTextGenerator\BBAI_Attribution;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CheckoutPluginParamTest extends TestCase {
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
				if ( ! empty( $GLOBALS['bbai_test_http_responses'] ) ) {
					return array_shift( $GLOBALS['bbai_test_http_responses'] );
				}
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

	private function client(): API_Client_V2 {
		$ref = new ReflectionClass( API_Client_V2::class );
		$api = $ref->newInstanceWithoutConstructor();
		$ref->getProperty( 'api_url' )->setValue( $api, 'https://example.test/api' );
		return $api;
	}

	public function test_checkout_body_carries_plugin_and_utm_tags(): void {
		$this->client()->create_checkout_session( 'price_test_1', 'https://example.com/ok', 'https://example.com/cancel', array( 'trigger_feature' => 'out_of_credits', 'plugin' => 'titles' ) );

		$this->assertCount( 1, $GLOBALS['bbai_test_http_requests'] );
		$request = $GLOBALS['bbai_test_http_requests'][0];
		$this->assertStringEndsWith( '/billing/checkout', $request['url'] );
		$body = json_decode( (string) $request['args']['body'], true );
		$this->assertSame( 'alt_text', $body['plugin'], 'plugin is always alt_text' );
		$this->assertSame( 'newsletter', $body['utm_source'] );
		$this->assertSame( 'email', $body['utm_medium'] );
		$this->assertSame( 'october', $body['utm_campaign'] );
		$this->assertSame( 'cta', $body['utm_content'] );
		$this->assertSame( 'alt text', $body['utm_term'] );
		$this->assertSame( 'price_test_1', $body['priceId'] );
		$this->assertSame( 'out_of_credits', $body['trigger_feature'] );
	}

	public function test_billing_trait_sends_plugin_and_never_retries_guest_route(): void {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/traits/trait-api-billing.php';
		$client = new class() {
			use \BeepBeepAI\AltTextGenerator\Traits\Api_Billing;
			public array $calls = array();
			public function get_site_fingerprint() { return 'fp'; }
			public function make_request( $endpoint, $method = 'GET', $data = null ) {
				$this->calls[] = array( $endpoint, $data );
				return new WP_Error( 'auth_expired', 'Session expired' );
			}
		};
		if ( ! function_exists( 'get_site_url' ) ) {
			function get_site_url() { return 'https://example.com'; }
		}
		$result = $client->create_checkout_session( 'price_test_1', 'https://example.com/ok', 'https://example.com/cancel', array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auth_expired', $result->get_error_code() );
		$this->assertCount( 1, $client->calls, 'no retry after an expired session' );
		$this->assertSame( '/billing/checkout', $client->calls[0][0] );
		$this->assertSame( 'alt_text', $client->calls[0][1]['plugin'] );
		$this->assertSame( 'newsletter', $client->calls[0][1]['utm_source'] );
	}

	public function test_checkout_body_has_no_referrer_or_landing_page(): void {
		$this->client()->create_checkout_session(
			'price_test_1',
			'https://example.com/ok',
			'https://example.com/cancel',
			array( 'referrer' => 'https://www.google.com/', 'landing_page' => '/wp-admin/admin.php', 'trigger_feature' => 'out_of_credits' )
		);
		$raw  = (string) $GLOBALS['bbai_test_http_requests'][0]['args']['body'];
		$body = json_decode( $raw, true );
		$this->assertArrayNotHasKey( 'referrer', $body );
		$this->assertArrayNotHasKey( 'landing_page', $body );
		$this->assertStringNotContainsString( 'google.com', $raw );
		$this->assertStringNotContainsString( 'wp-admin/admin.php', $raw );
		$this->assertSame( 'newsletter', $body['utm_source'] );
		$this->assertArrayNotHasKey( 'referrer', BBAI_Attribution::for_checkout( BBAI_Attribution::get_payload() ) );
		$this->assertArrayNotHasKey( 'landing_page', BBAI_Attribution::for_checkout( BBAI_Attribution::get_payload() ) );
	}

	public function test_user_not_found_retry_keeps_utm_site_install_id_and_plugin(): void {
		if ( ! function_exists( 'delete_transient' ) ) {
			function delete_transient( $key ) { return true; }
		}
		$api = new class() extends API_Client_V2 {
			public function __construct() {}
			public function get_token() { return $GLOBALS['bbai_test_token'] ?? ''; }
			public function clear_token() { $GLOBALS['bbai_test_token'] = ''; }
		};
		( new ReflectionProperty( API_Client_V2::class, 'api_url' ) )->setValue( $api, 'https://example.test/api' );
		$GLOBALS['bbai_test_token']          = 'jwt-test';
		$GLOBALS['bbai_test_http_responses'] = array(
			array( 'status' => 500, 'body' => array( 'success' => false, 'error' => 'User not found' ) ),
		);

		$api->create_checkout_session( 'price_test_1', 'https://example.com/ok', 'https://example.com/cancel', array( 'referrer' => 'https://www.google.com/' ) );

		$this->assertCount( 2, $GLOBALS['bbai_test_http_requests'], 'one retry after user not found' );
		$retry = $GLOBALS['bbai_test_http_requests'][1];
		$this->assertStringEndsWith( '/billing/checkout', $retry['url'] );
		$body = json_decode( (string) $retry['args']['body'], true );
		$this->assertSame( 'alt_text', $body['plugin'] );
		$this->assertSame( 'price_test_1', $body['priceId'] );
		foreach ( array( 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'october', 'utm_content' => 'cta', 'utm_term' => 'alt text' ) as $k => $v ) {
			$this->assertSame( $v, $body[ $k ], $k );
		}
		// sha256( 'oppti-install-v1:' . the bootstrap site identifier ), never the raw identifier.
		$this->assertSame( '61cf62dcdec8b141d4415c712d64be5953f8f35b0ec8ed67137508be91c9592e', $body['site_install_id'] );
		$this->assertStringNotContainsString( 'test_site_install_id_0123456789ab', (string) $retry['args']['body'] );
		$this->assertArrayNotHasKey( 'referrer', $body );
		$this->assertArrayNotHasKey( 'landing_page', $body );
		$this->assertArrayNotHasKey( 'Authorization', (array) ( $retry['args']['headers'] ?? array() ), 'retry goes without the token' );
	}

	public function test_no_shipped_code_calls_the_guest_checkout_route(): void {
		$hits = array();
		foreach ( array( 'includes', 'admin', 'assets/js' ) as $dir ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( BEEPBEEP_AI_PLUGIN_DIR . $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( preg_match( '/\.(php|js)$/', $file->getFilename() ) && false !== strpos( (string) file_get_contents( $file->getPathname() ), 'billing/checkout/guest' ) ) {
					$hits[] = $file->getPathname();
				}
			}
		}
		$this->assertSame( array(), $hits );
	}
}
