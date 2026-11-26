<?php
/**
 * 4.6.147: a Free user on the Usage page (a Growth paywall) still sees when
 * their credits refill, with the same line the ALT Library banner uses, and
 * nothing else from the locked preview is unlocked.
 */
declare(strict_types=1);

namespace {
	use PHPUnit\Framework\TestCase;

	final class FreeUsageResetDateTest extends TestCase {
		private const TPL = 'admin/partials/credit-usage-content.php';

		protected function setUp(): void {
			if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
			foreach ( array( '__', 'esc_html', 'esc_url', 'esc_attr' ) as $fn ) {
				if ( ! function_exists( $fn ) ) {
					eval( 'function ' . $fn . '( $s, $d = null ) { return $s; }' );
				}
			}
			foreach ( array( 'esc_html_e', 'esc_attr_e' ) as $fn ) {
				if ( ! function_exists( $fn ) ) {
					eval( 'function ' . $fn . '( $s, $d = null ) { echo $s; }' );
				}
			}
			if ( ! function_exists( 'admin_url' ) ) {
				function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' ); }
			}
			if ( ! function_exists( 'bbai_usage_reset_label' ) ) {
				// The shipped helper, with wp_date() swapped for gmdate() (other tests stub wp_date without a timestamp).
				$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php' );
				$start = strpos( $src, 'function bbai_usage_reset_label(' );
				$this->assertNotFalse( $start );
				$end = strpos( $src, "\n}\n", $start );
				eval( str_replace( 'wp_date(', 'gmdate(', substr( $src, $start, $end - $start + 2 ) ) );
			}
		}

		/** The locked (Free) overlay block of the Usage template. */
		private function overlay(): string {
			$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . self::TPL );
			$start = strpos( $src, "<?php if (\$bbai_usage_is_locked) : ?>\n    </div>" );
			$this->assertNotFalse( $start, 'locked overlay block' );
			$end = strpos( $src, '<?php endif; ?>', strpos( $src, 'bbai-paywall-preview__actions', $start ) );
			return substr( $src, $start, $end - $start + strlen( '<?php endif; ?>' ) );
		}

		private function render( bool $locked, array $usage ): string {
			$code = $this->overlay();
			return ( static function ( string $bbai_code, bool $bbai_usage_is_locked, array $current_usage ): string {
				ob_start();
				eval( '?>' . $bbai_code );
				return (string) ob_get_clean();
			} )( $code, $locked, $usage );
		}

		public function test_free_overlay_shows_refill_date(): void {
			$ts   = time() + 5 * 86400;
			$html = $this->render( true, array( 'plan' => 'free', 'reset_timestamp' => $ts ) );
			$this->assertStringContainsString( 'Unlock Usage insights', $html );
			$this->assertStringContainsString( 'data-bbai-usage-reset-date="1"', $html );
			$this->assertStringContainsString( 'Your credits refill on ' . gmdate( 'F j, Y', $ts ) . '.', $html );
		}

		public function test_reset_date_string_falls_back_to_reset_date_field(): void {
			$ts   = strtotime( '+10 days 12:00' );
			$html = $this->render( true, array( 'resetDate' => gmdate( 'Y-m-d', $ts ) ) );
			$this->assertStringContainsString( 'Your credits refill on ' . gmdate( 'F j, Y', $ts ) . '.', $html );
		}

		public function test_no_line_without_a_usable_date(): void {
			$html = $this->render( true, array( 'reset_timestamp' => time() - 60 ) );
			$this->assertStringNotContainsString( 'data-bbai-usage-reset-date', $html );
			$this->assertStringContainsString( 'Unlock Usage insights', $html, 'paywall unchanged' );
		}

		public function test_paid_plans_get_no_overlay_and_no_extra_line(): void {
			$html = $this->render( false, array( 'reset_timestamp' => time() + 86400 ) );
			$this->assertSame( '', trim( $html ) );
		}

		public function test_same_line_as_library_banner_and_paywall_still_wraps_the_insights(): void {
			$banner = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php' );
			$this->assertStringContainsString( "__('Your credits refill on %s.', 'beepbeep-ai-alt-text-generator')", $banner );
			$this->assertStringContainsString( "return bbai_usage_reset_label(\$usage);", $banner );
			$tpl = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . self::TPL );
			$this->assertStringContainsString( "__('Your credits refill on %s.', 'beepbeep-ai-alt-text-generator')", $tpl );
			// Locked preview content stays aria-hidden behind the overlay.
			$this->assertStringContainsString( "<div class=\"bbai-paywall-preview__content\" aria-hidden=\"true\">", $tpl );
			$this->assertStringContainsString( "\$bbai_usage_is_locked    = !in_array(\$bbai_usage_plan_slug, ['starter', 'pro', 'growth', 'agency', 'enterprise'], true);", $tpl );
		}
	}
}
