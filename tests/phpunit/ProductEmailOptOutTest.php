<?php
/**
 * Coverage for the sign-up "Don't send me product tips and plan emails" checkbox.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\API_Client_V2;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $value ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! class_exists( API_Client_V2::class, false ) ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
}

final class ProductEmailOptOutTest extends TestCase {
	private array $saved_post = array();

	protected function setUp(): void {
		$this->saved_post = $_POST;
	}

	protected function tearDown(): void {
		$_POST = $this->saved_post;
	}

	public function test_ticked_values_opt_out(): void {
		foreach ( array( '1', 'true', 'on', 'yes', true ) as $value ) {
			$this->assertTrue( API_Client_V2::sanitize_product_emails_opt_out( $value ) );
		}
	}

	public function test_unticked_values_do_not_opt_out(): void {
		foreach ( array( '0', 'false', '', 'off', false ) as $value ) {
			$this->assertFalse( API_Client_V2::sanitize_product_emails_opt_out( $value ) );
		}
	}

	public function test_absent_or_junk_is_not_forwarded(): void {
		$this->assertNull( API_Client_V2::sanitize_product_emails_opt_out( null ) );
		$this->assertNull( API_Client_V2::sanitize_product_emails_opt_out( 'maybe' ) );
		$this->assertNull( API_Client_V2::sanitize_product_emails_opt_out( array( '1' ) ) );
	}

	public function test_request_reader(): void {
		$_POST = array();
		$this->assertSame( array(), API_Client_V2::get_product_emails_opt_out_from_request() );
		$_POST = array( 'product_emails_opt_out' => '1' );
		$this->assertSame( array( 'product_emails_opt_out' => true ), API_Client_V2::get_product_emails_opt_out_from_request() );
		$_POST = array( 'product_emails_opt_out' => '0' );
		$this->assertSame( array( 'product_emails_opt_out' => false ), API_Client_V2::get_product_emails_opt_out_from_request() );
	}

	public function test_modal_checkbox_is_unticked_escaped_and_sent(): void {
		$js = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'assets/js/auth-modal.js' );
		$this->assertMatchesRegularExpression( '/<input type="checkbox" id="register-product-emails-opt-out" name="product_emails_opt_out" value="1">/', $js );
		$this->assertDoesNotMatchRegularExpression( '/register-product-emails-opt-out"[^>]*checked/', $js );
		$this->assertStringContainsString( "this.escapeHtml(this.t(\"Don't send me product tips and plan emails\"))", $js );
		$this->assertStringContainsString( 'product_emails_opt_out: this.getProductEmailOptOutValue(formData)', $js );
	}
}
