<?php
/**
 * Admin country used for checkout currency: the WP admin, not the hosting server.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\BBAI_Admin_Country;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $value ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'get_user_locale' ) ) {
	function get_user_locale() {
		return $GLOBALS['bbai_test_user_locale'] ?? 'en_US';
	}
}
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-admin-country.php';

final class CheckoutAdminCountryTest extends TestCase {
	private array $server_backup = array();

	protected function setUp(): void {
		$this->server_backup = $_SERVER;
		foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY' ) as $k ) {
			unset( $_SERVER[ $k ] );
		}
		$GLOBALS['bbai_test_options']['timezone_string'] = '';
		$GLOBALS['bbai_test_user_locale']                = 'en_US';
		BBAI_Admin_Country::reset_cache();
	}

	protected function tearDown(): void {
		$_SERVER = $this->server_backup;
		BBAI_Admin_Country::reset_cache();
	}

	public function test_edge_header_wins(): void {
		$_SERVER['HTTP_CF_IPCOUNTRY']                    = 'pl';
		$GLOBALS['bbai_test_options']['timezone_string'] = 'America/New_York';
		$this->assertSame( array( 'country' => 'PL', 'source' => 'HTTP_CF_IPCOUNTRY' ), BBAI_Admin_Country::resolve() );
	}

	public function test_unknown_edge_value_falls_through_to_timezone(): void {
		$_SERVER['HTTP_CF_IPCOUNTRY']                    = 'XX';
		$GLOBALS['bbai_test_options']['timezone_string'] = 'Europe/Warsaw';
		$this->assertSame( array( 'country' => 'PL', 'source' => 'timezone' ), BBAI_Admin_Country::resolve() );
	}

	public function test_locale_region_when_no_timezone_city(): void {
		$GLOBALS['bbai_test_options']['timezone_string'] = 'UTC+1';
		$GLOBALS['bbai_test_user_locale']                = 'pl_PL';
		$this->assertSame( 'PL', BBAI_Admin_Country::country() );
	}

	public function test_default_en_us_locale_is_not_a_country_signal(): void {
		$this->assertSame( array( 'country' => '', 'source' => '' ), BBAI_Admin_Country::resolve() );
	}

	public function test_us_timezone_is_us(): void {
		$GLOBALS['bbai_test_options']['timezone_string'] = 'America/Chicago';
		$this->assertSame( 'US', BBAI_Admin_Country::country() );
	}

	public function test_invalid_timezone_is_ignored(): void {
		$GLOBALS['bbai_test_options']['timezone_string'] = 'Bogus/Zone';
		$GLOBALS['bbai_test_user_locale']                = 'de_DE';
		$this->assertSame( 'DE', BBAI_Admin_Country::country() );
	}
}
