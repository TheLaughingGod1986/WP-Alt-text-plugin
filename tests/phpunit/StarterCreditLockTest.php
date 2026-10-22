<?php
/**
 * 4.6.143 (QA FAIL on b800b98b, items 4-5): Starter at 0 credits is locked on the
 * Dashboard and gets the upsell, and usage reports upgrade_required. The lock flags
 * use the 4.6.142 lists; is_paid() stays for paid-status copy and counters.
 */
declare(strict_types=1);

namespace {
	require_once __DIR__ . '/stubs-template-snippets.php';
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-dashboard-state.php';

	use BeepBeepAI\AltTextGenerator\Plan_Tier;
	use BeepBeepAI\AltTextGenerator\Services\Dashboard_State;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\TestCase;

	final class StarterCreditLockTest extends TestCase {
		private const BODY = 'admin/partials/dashboard-body.php';

		/** Dashboard lock flag exactly as dashboard-body.php computes it. */
		private function body_flags( string $plan, string $ladder ): array {
			$code = array();
			foreach ( array( '$bbai_is_agency =', '$bbai_usage_can_autopilot =', '$bbai_is_premium =', '$bbai_skips_credit_lock =' ) as $m ) {
				$code[] = Bbai_Template_Snippet::statement( self::BODY, $m );
			}
			// Plan_Helpers::get_plan_data()['is_pro'], as class-plan-helpers.php computes it.
			$helper = Bbai_Template_Snippet::run(
				array( Bbai_Template_Snippet::statement( 'includes/admin/class-plan-helpers.php', '$is_pro = \\BeepBeepAI' ) ),
				array( 'plan_slug' => $plan )
			);
			return Bbai_Template_Snippet::run(
				$code,
				array(
					'bbai_is_guest_trial'   => false,
					'bbai_plan_slug_ladder' => $ladder,
					'bbai_plan_data'        => array( 'plan_slug' => $plan, 'is_agency' => 'agency' === $plan, 'is_pro' => $helper['is_pro'] ),
					'bbai_usage_stats'      => array( 'plan' => $plan ),
				)
			);
		}

		private function resolve( string $plan, string $ladder, int $remaining, int $limit ): array {
			$flags = $this->body_flags( $plan, $ladder );
			return Dashboard_State::resolve(
				array(
					'is_guest_trial' => false,
					'is_premium'     => $flags['bbai_skips_credit_lock'],
					'plan_slug'      => $ladder,
					'usage_stats'    => array( 'plan' => $plan, 'limit' => $limit, 'remaining' => $remaining, 'used' => $limit - $remaining ),
					'trial_status'   => array( 'limit' => 10, 'used' => 0 ),
					'missing_count'  => 12,
					'weak_count'     => 0,
					'total_count'    => 40,
				)
			);
		}

		public function test_starter_at_zero_credits_is_locked_with_the_upsell(): void {
			$flags = $this->body_flags( 'starter', 'starter' );
			$this->assertTrue( $flags['bbai_is_premium'], 'Starter keeps paid status for copy and quota type' );
			$this->assertFalse( $flags['bbai_skips_credit_lock'] );

			$state = $this->resolve( 'starter', 'starter', 0, 100 );
			$this->assertFalse( $state['flags']['generate_available'] );
			$this->assertFalse( $state['flags']['generate_enabled'] );
			$this->assertTrue( $state['flags']['lock_generation_actions'] );
			$this->assertSame( 'upgrade_growth', $state['cta']['locked_mode'] ?? $state['locked_cta_mode'] ?? $this->locked_mode( $state ) );
		}

		public function test_starter_with_credits_left_can_generate(): void {
			$state = $this->resolve( 'starter', 'starter', 40, 100 );
			$this->assertTrue( $state['flags']['generate_available'] );
			$this->assertFalse( $state['flags']['lock_generation_actions'] );
		}

		public function test_free_at_zero_is_locked_as_before(): void {
			$state = $this->resolve( 'free', 'free', 0, 25 );
			$this->assertTrue( $state['flags']['lock_generation_actions'] );
			$this->assertSame( 'upgrade_growth', $this->locked_mode( $state ) );
		}

		public static function skip_plans(): array {
			// dashboard-body maps growth to 'pro' on the ladder; 4.6.142 list is pro, agency.
			return array( 'growth' => array( 'growth', 'pro' ), 'pro' => array( 'pro', 'pro' ), 'agency' => array( 'agency', 'agency' ) );
		}

		#[DataProvider( 'skip_plans' )]
		public function test_plans_that_skipped_the_dashboard_lock_in_4_6_142_still_do( string $plan, string $ladder ): void {
			$this->assertTrue( $this->body_flags( $plan, $ladder )['bbai_skips_credit_lock'] );
			$state = $this->resolve( $plan, $ladder, 0, 1000 );
			$this->assertTrue( $state['flags']['generate_available'] );
			$this->assertFalse( $state['flags']['lock_generation_actions'] );
		}

		public function test_dashboard_resolve_and_banner_nudge_use_the_lock_flag(): void {
			$src = Bbai_Template_Snippet::src( self::BODY );
			$this->assertStringContainsString( "'is_premium'     => \$bbai_skips_credit_lock,", $src );
			$this->assertStringContainsString( "'skipsCreditLock' => \$bbai_skips_credit_lock,", $src );
			$this->assertStringContainsString( "'skips_credit_lock'      => \$bbai_skips_credit_lock,", $src );
			$banner = Bbai_Template_Snippet::src( 'includes/admin/banner-system.php' );
			$this->assertStringContainsString( "!empty(\$s['skips_credit_lock'] ?? \$s['is_pro_plan'] ?? false)) {", $banner );
		}

		/** Helper: locked CTA mode wherever resolve() puts it. */
		private function locked_mode( array $state ): string {
			foreach ( array( array( 'cta', 'locked_mode' ), array( 'cta', 'locked_cta_mode' ), array( 'locked_cta_mode' ) ) as $path ) {
				$node = $state;
				foreach ( $path as $k ) {
					if ( ! is_array( $node ) || ! array_key_exists( $k, $node ) ) {
						$node = null;
						break;
					}
					$node = $node[ $k ];
				}
				if ( is_string( $node ) ) {
					return $node;
				}
			}
			$this->fail( 'locked CTA mode not found in Dashboard_State::resolve output' );
		}

		/* ---- REST state truth ---- */

		public static function rest_plans(): array {
			return array(
				'starter' => array( 'starter', false, false ), 'free' => array( 'free', false, false ),
				'pro' => array( 'pro', true, true ), 'growth' => array( 'growth', true, true ),
				'agency' => array( 'agency', true, true ), 'enterprise' => array( 'enterprise', false, true ),
			);
		}

		/** Lists as on a13d9b49: plan context pro/growth/agency; credits block adds enterprise. */
		#[DataProvider( 'rest_plans' )]
		public function test_rest_is_pro_uses_the_4_6_142_lists( string $plan, bool $ctx_pro, bool $credits_pro ): void {
			$rel = 'admin/class-bbai-rest-controller.php';
			$ctx = Bbai_Template_Snippet::run(
				array( Bbai_Template_Snippet::statement( $rel, '$is_pro                = Plan_Tier::' ) ),
				array( 'plan_slug' => $plan, 'usage_stats' => array(), 'auth_state' => array() )
			);
			$this->assertSame( $ctx_pro, $ctx['is_pro'] );
			$credits = Bbai_Template_Snippet::run(
				array( Bbai_Template_Snippet::statement( $rel, '$is_pro    = Plan_Tier::' ) ),
				array( 'plan_slug' => $plan, 'usage_stats' => array() )
			);
			$this->assertSame( $credits_pro, $credits['is_pro'] );
			$this->assertStringNotContainsString( 'Plan_Tier::is_paid( $plan_slug )', Bbai_Template_Snippet::src( $rel ) );
		}

		/** Hero JS: the lock/upsell flag drops Starter, paid-status copy keeps it. */
		public function test_hero_js_splits_lock_flag_from_paid_copy(): void {
			$src = Bbai_Template_Snippet::src( 'admin/partials/dashboard-logged-in-hero.php' );
			$this->assertStringContainsString( "if ( ! isPro && [ 'pro', 'growth', 'agency', 'enterprise' ].indexOf( plan ) !== -1 ) {", $src );
			$this->assertStringContainsString( "var isPaid = isPro || !! ( credits.is_paid || credits.isPaid ) || [ 'starter', 'growth', 'pro', 'agency', 'enterprise' ].indexOf( plan ) !== -1;", $src );
			$this->assertSame( 2, substr_count( $src, 'var isFirstFreeGeneration = credits && ! credits.isPaid && credits.used' ) );
			$this->assertStringContainsString( "root.setAttribute( 'data-bbai-is-premium', credits.isPro ? '1' : '0' );", $src );
		}

		/* ---- 5. upgrade_required in usage ---- */

		private function usage( string $plan, int $remaining, array $live = array() ): array {
			$rel  = 'includes/services/class-usage-helper.php';
			$code = array(
				Bbai_Template_Snippet::statement( $rel, "\$usage_stats['is_pro'] = " ),
				Bbai_Template_Snippet::statement( $rel, "\$usage_stats['upgrade_required'] = " ),
			);
			return Bbai_Template_Snippet::run(
				$code,
				array( 'usage_stats' => array(), 'plan' => $plan, 'remaining' => $remaining, 'quota_type' => Plan_Tier::is_paid( $plan ) ? 'paid' : 'monthly_account', 'live_usage' => $live )
			)['usage_stats'];
		}

		public function test_starter_at_zero_needs_an_upgrade_when_backend_omits_it(): void {
			$u = $this->usage( 'starter', 0 );
			$this->assertFalse( $u['is_pro'] );
			$this->assertTrue( $u['upgrade_required'] );
			$this->assertFalse( $this->usage( 'starter', 10 )['upgrade_required'] );
			$this->assertFalse( $this->usage( 'starter', 0, array( 'upgrade_required' => false ) )['upgrade_required'], 'backend value wins' );
		}

		public static function usage_plans(): array {
			return array( 'free' => array( 'free', true ), 'pro' => array( 'pro', false ), 'growth' => array( 'growth', false ), 'agency' => array( 'agency', false ), 'enterprise' => array( 'enterprise', false ) );
		}

		#[DataProvider( 'usage_plans' )]
		public function test_other_plans_upgrade_required_as_in_4_6_142( string $plan, bool $required ): void {
			$this->assertSame( $required, $this->usage( $plan, 0 )['upgrade_required'] );
		}

		public function test_lock_lists_match_4_6_142(): void {
			$this->assertSame( array( 'pro', 'growth', 'agency', 'enterprise' ), Plan_Tier::CREDIT_LOCK_EXEMPT );
			$this->assertFalse( Plan_Tier::skips_credit_lock( 'starter' ) );
			$this->assertTrue( Plan_Tier::is_paid( 'starter' ) );
			$src = Bbai_Template_Snippet::src( 'includes/services/class-usage-helper.php' );
			$this->assertStringContainsString( "(\\BeepBeepAI\\AltTextGenerator\\Plan_Tier::is_paid(\$plan) ? 'paid' : 'monthly_account')", $src, 'quota type stays paid for Starter' );
		}

		/* ---- Nit: prefixed template variable ---- */

		public function test_upgrade_modal_has_no_unprefixed_assignment(): void {
			$tpl = Bbai_Template_Snippet::src( 'templates/upgrade-modal.php' );
			$this->assertDoesNotMatchRegularExpression( '/^\$checkout_prices\s*=/m', $tpl );
			$this->assertStringContainsString( "\$bbai_starter_price_id = \$bbai_upgrade_checkout_prices['starter'] ?? '';", $tpl );
		}

		/* ---- Starter limit constant read from the class that defines it ---- */

		public function test_starter_monthly_limit_reads_api_client_v2(): void {
			$src = Bbai_Template_Snippet::src( 'admin/traits/trait-core-assets.php' );
			$this->assertStringContainsString( "defined( \\BeepBeepAI\\AltTextGenerator\\API_Client_V2::class . '::STARTER_MONTHLY_LIMIT' ) ? \\BeepBeepAI\\AltTextGenerator\\API_Client_V2::STARTER_MONTHLY_LIMIT : 100;", $src );
			$this->assertStringNotContainsString( 'Usage_Tracker::STARTER_MONTHLY_LIMIT', $src );
		}
	}
}
