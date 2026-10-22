<?php
/**
 * 4.6.143: the no-JS checkout pages are reachable and their error is visible.
 * Source-level checks (Core needs a full WordPress to construct).
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CheckoutNoJsReachableTest extends TestCase {
	private function src( string $rel ): string {
		return (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . $rel );
	}

	public function test_checkout_error_notice_survives_the_notice_hider(): void {
		$core = $this->src( 'admin/class-bbai-core.php' );
		$this->assertMatchesRegularExpression( '/<div class="notice notice-error bbai-preserve-notice">\s*<p>\s*<strong><\?php esc_html_e\(\'Unable to start checkout\'/', $core );
	}

	public function test_direct_checkout_accepts_generated_and_legacy_nonces(): void {
		$core  = $this->src( 'admin/class-bbai-core.php' );
		$start = strpos( $core, 'public function maybe_handle_direct_checkout()' );
		$this->assertNotFalse( $start );
		$body = substr( $core, $start, 2500 );
		$this->assertStringContainsString( "wp_verify_nonce( \$direct_nonce, 'bbai_direct_checkout' )", $body );
		$this->assertStringContainsString( "wp_verify_nonce( \$legacy_nonce, 'bbai_checkout_redirect' )", $body );
		// The URL builder generates the nonce the handler verifies.
		$this->assertMatchesRegularExpression( "/function get_direct_checkout_url\(string \\\$plan\): string \{.*?'bbai_direct_checkout',\s*'_bbai_nonce'/s", $core );
	}

	public function test_upgrade_modal_reads_the_price_map_callers_pass(): void {
		$tpl = $this->src( 'templates/upgrade-modal.php' );
		$this->assertStringContainsString( '$bbai_upgrade_checkout_prices = isset($bbai_checkout_prices) && is_array($bbai_checkout_prices)', $tpl );
		foreach ( array( 'admin/class-bbai-core.php', 'admin/traits/trait-core-admin-ui.php' ) as $caller ) {
			$this->assertStringContainsString( '$bbai_checkout_prices = $this->get_checkout_price_ids();', $this->src( $caller ), $caller );
		}
	}

	public function test_starter_nudge_carries_price_id_and_no_js_href(): void {
		$banner = $this->src( 'includes/admin/banner-system.php' );
		$this->assertStringContainsString( "Core::get_resolved_checkout_price_id('starter')", $banner );
		$this->assertStringContainsString( "\$action['href'] = \\BeepBeepAI\\AltTextGenerator\\Core::get_direct_checkout_url('starter');", $banner );
	}
}
