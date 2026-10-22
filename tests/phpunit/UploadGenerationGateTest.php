<?php
/**
 * 4.6.143 (QA blocker): upload generation keeps its own gate. Starter is paid but
 * does not include it; the allowed list is the 4.6.142 one.
 */
declare(strict_types=1);

namespace {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-plan-tier.php';

	use BeepBeepAI\AltTextGenerator\Plan_Tier;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\TestCase;

	final class UploadGenerationGateTest extends TestCase {
		public static function plans(): array {
			return array(
				'starter'    => array( 'starter', false ),
				'free'       => array( 'free', false ),
				'trial'      => array( 'trial', false ),
				'empty'      => array( '', false ),
				'pro'        => array( 'pro', true ),
				'growth'     => array( 'growth', true ),
				'agency'     => array( 'agency', true ),
				'enterprise' => array( 'enterprise', true ),
			);
		}

		#[DataProvider( 'plans' )]
		public function test_upload_generation_gate( string $plan, bool $allowed ): void {
			$this->assertSame( $allowed, Plan_Tier::can_use_upload_generation( $plan ) );
		}

		public function test_list_matches_4_6_142_and_starter_is_still_paid(): void {
			$this->assertSame( array( 'pro', 'growth', 'agency', 'enterprise' ), Plan_Tier::UPLOAD_GENERATION_PLANS );
			$this->assertTrue( Plan_Tier::is_paid( 'starter' ) );
			$this->assertFalse( Plan_Tier::can_use_upload_generation( 'starter' ) );
		}

		public function test_core_upload_gate_does_not_use_is_paid(): void {
			$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php' );
			$start = strpos( $src, 'private function is_upload_generation_plan_slug(' );
			$this->assertNotFalse( $start );
			$end  = strpos( $src, 'return false;', strpos( $src, 'private function current_account_can_use_upload_generation(' ) );
			$body = substr( $src, $start, $end - $start );
			$this->assertStringContainsString( 'Plan_Tier::can_use_upload_generation($plan)', $body );
			$this->assertStringNotContainsString( 'is_paid', $body );
			$this->assertSame( 3, substr_count( $body, '$this->is_upload_generation_plan_slug(' ) );
		}
	}
}
