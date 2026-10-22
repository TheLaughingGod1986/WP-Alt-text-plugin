<?php
/**
 * 4.6.143 (CoS): Starter hits the out-of-credits lock like the backend.
 * Only the plans that skipped it before 4.6.143 still skip it.
 */
declare(strict_types=1);

namespace {
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }
	}
	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {}
	}
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-plan-tier.php';
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/traits/trait-api-usage.php';

	use BeepBeepAI\AltTextGenerator\Plan_Tier;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\TestCase;

	final class BbaiLimitProbe {
		use \BeepBeepAI\AltTextGenerator\Traits\Api_Usage;
		public array $usage = array();
		public function get_usage( $force_refresh = false ) { return $this->usage; }
	}

	final class CreditLockPlanTest extends TestCase {
		private function reached( string $plan, int $used, int $limit ): bool {
			$p        = new BbaiLimitProbe();
			$p->usage = array( 'plan' => $plan, 'used' => $used, 'limit' => $limit, 'remaining' => max( 0, $limit - $used ) );
			return $p->has_reached_limit();
		}

		public function test_starter_with_zero_remaining_has_reached_limit(): void {
			$this->assertTrue( $this->reached( 'starter', 100, 100 ) );
			$this->assertFalse( $this->reached( 'starter', 40, 100 ) );
		}

		public function test_starter_is_not_exempt_from_the_library_lock(): void {
			$this->assertFalse( Plan_Tier::skips_library_credit_lock( 'starter' ) );
			$this->assertFalse( Plan_Tier::skips_limit_check( 'starter' ) );
			$this->assertTrue( Plan_Tier::is_paid( 'starter' ), 'Starter still counts as paid for copy and counters' );
		}

		/** Sets exactly as on a13d9b49 (4.6.142). */
		public function test_previously_skipping_plans_are_unchanged(): void {
			$this->assertSame( array( 'pro', 'growth', 'agency' ), Plan_Tier::LIBRARY_CREDIT_LOCK_EXEMPT );
			$this->assertSame( array( 'pro', 'agency' ), Plan_Tier::LIMIT_CHECK_EXEMPT );
			$this->assertFalse( $this->reached( 'pro', 25, 25 ) );
			$this->assertFalse( $this->reached( 'agency', 25, 25 ) );
			$this->assertTrue( $this->reached( 'growth', 25, 25 ), 'growth was not exempt in has_reached_limit before 4.6.143' );
			$this->assertTrue( $this->reached( 'free', 25, 25 ) );
		}

		public static function library_plans(): array {
			return array(
				'starter' => array( 'starter', false ), 'free' => array( 'free', false ), 'trial' => array( 'trial', false ),
				'enterprise' => array( 'enterprise', false ), 'pro' => array( 'pro', true ), 'growth' => array( 'growth', true ),
				'agency' => array( 'agency', true ),
			);
		}

		#[DataProvider( 'library_plans' )]
		public function test_library_lock_exemption( string $plan, bool $skips ): void {
			$this->assertSame( $skips, Plan_Tier::skips_library_credit_lock( $plan ) );
		}

		public function test_library_tab_uses_the_lock_helper_not_is_paid(): void {
			$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/library-tab.php' );
			$this->assertSame( 2, substr_count( $src, 'Plan_Tier::skips_library_credit_lock($bbai_plan)' ) );
			$this->assertStringNotContainsString( 'Plan_Tier::is_paid($bbai_plan)', $src );
		}
	}
}
