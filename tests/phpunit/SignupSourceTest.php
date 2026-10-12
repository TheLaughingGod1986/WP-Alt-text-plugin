<?php
/**
 * Coverage for the optional "How did you find us?" sign-up fields.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\API_Client_V2;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $value ) );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $value ) ) );
	}
}
if ( ! class_exists( API_Client_V2::class, false ) ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
}

final class SignupSourceTest extends TestCase {
	public function test_blank_sends_nothing(): void {
		$this->assertSame( array(), API_Client_V2::sanitize_signup_source( '', 'text' ) );
	}

	public function test_unknown_value_is_dropped(): void {
		$this->assertSame( array(), API_Client_V2::sanitize_signup_source( 'tiktok', '' ) );
	}

	public function test_valid_values_pass_and_ignore_other_text(): void {
		foreach ( array( 'wporg', 'search', 'x', 'facebook_group', 'friend' ) as $value ) {
			$this->assertSame( array( 'signup_source' => $value ), API_Client_V2::sanitize_signup_source( $value, 'ignored' ) );
		}
	}

	public function test_codes_are_the_shared_fixed_set(): void {
		$this->assertSame( array( 'wporg', 'search', 'x', 'facebook_group', 'friend', 'other' ), API_Client_V2::get_signup_source_values() );
	}

	public function test_legacy_codes_are_dropped(): void {
		foreach ( array( 'wporg_search', 'google', 'ai_assistant', 'oppti_site', 'friend_colleague', 'social' ) as $value ) {
			$this->assertSame( array(), API_Client_V2::sanitize_signup_source( $value, '' ) );
		}
	}

	public function test_other_text_is_stripped_and_capped(): void {
		$out = API_Client_V2::sanitize_signup_source( 'other', '<b>Podcast</b><script>x</script>' . str_repeat( 'a', 300 ) );
		$this->assertSame( 'other', $out['signup_source'] );
		$this->assertStringStartsWith( 'Podcast', $out['signup_source_other'] );
		$this->assertStringNotContainsString( '<', $out['signup_source_other'] );
		$this->assertLessThanOrEqual( 200, mb_strlen( $out['signup_source_other'] ) );
	}
}
