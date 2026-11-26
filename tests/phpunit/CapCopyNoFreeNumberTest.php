<?php
/**
 * 4.6.147 copy (Marketing, Chief-approved): "free" no longer sits next to a
 * number in the dashboard cap note and the limit-reached reset line, in the
 * PHP template, the JS source and the shipped .min.js.
 */
declare(strict_types=1);

namespace {
	use PHPUnit\Framework\TestCase;

	final class CapCopyNoFreeNumberTest extends TestCase {
		private function src( string $rel ): string {
			return (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . $rel );
		}

		public function test_dashboard_cap_note(): void {
			$new = 'You’ve used all 25 generations included in your plan this month. Upgrade for more, or wait for the monthly reset.';
			foreach ( array( 'admin/partials/dashboard-logged-in-page.php', 'assets/js/bbai-admin.js', 'assets/js/bbai-admin.min.js' ) as $rel ) {
				$s = $this->src( $rel );
				$this->assertStringContainsString( $new, $s, $rel );
				$this->assertStringNotContainsString( '25 free generations', $s, $rel );
				$this->assertStringNotContainsString( 'generations a month on your current plan', $s, $rel );
			}
		}

		public function test_limit_reached_reset_line(): void {
			foreach ( array( 'assets/js/bbai-admin.js', 'assets/js/bbai-admin.min.js' ) as $rel ) {
				$s = $this->src( $rel );
				$this->assertStringContainsString( 'Your next %d credits will be available at the next monthly reset.', $s, $rel );
				$this->assertStringNotContainsString( 'free credits will be available', $s, $rel );
				$this->assertStringNotContainsString( 'free credits should be available', $s, $rel );
				$this->assertStringNotContainsString( 'next %d free credits', $s, $rel );
			}
		}
	}
}
