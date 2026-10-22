<?php
/**
 * 4.6.143 (QA FAIL on b800b98b, items 1-3): Starter is paid, but it does not get
 * the Admin tab, the Autopilot CTAs, or automation / Growth capacity copy.
 * Growth, Pro and Agency keep what they had in 4.6.142.
 */
declare(strict_types=1);

namespace {
	require_once __DIR__ . '/stubs-template-snippets.php';

	use BeepBeepAI\AltTextGenerator\Plan_Tier;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\TestCase;

	final class StarterFeatureGateTest extends TestCase {
		private const PAGE = 'admin/partials/dashboard-logged-in-page.php';
		private const BODY = 'admin/partials/dashboard-body.php';

		/* ---- 1. Admin tab ---- */

		public static function admin_tab_plans(): array {
			return array(
				'starter' => array( 'starter', false ), 'free' => array( 'free', false ), 'trial' => array( 'trial', false ),
				'growth' => array( 'growth', false ), 'enterprise' => array( 'enterprise', false ),
				'pro' => array( 'pro', true ), 'agency' => array( 'agency', true ),
			);
		}

		/** Same list as a13d9b49: ($bbai_plan_slug === 'pro' || $bbai_plan_slug === 'agency'). */
		#[DataProvider( 'admin_tab_plans' )]
		public function test_admin_tab_gate( string $plan, bool $allowed ): void {
			$this->assertSame( $allowed, Plan_Tier::can_see_admin_tab( $plan ) );
			foreach ( array( 'admin/class-bbai-core.php', 'admin/traits/trait-core-admin-ui.php' ) as $rel ) {
				$vars = Bbai_Template_Snippet::run(
					array( Bbai_Template_Snippet::statement( $rel, '$bbai_is_pro = \\BeepBeepAI' ) ),
					array( 'bbai_plan_slug' => $plan )
				);
				$this->assertSame( $allowed, $vars['bbai_is_pro'], "$rel for $plan" );
			}
		}

		public function test_admin_tab_uses_its_own_helper_not_is_paid(): void {
			$this->assertSame( array( 'pro', 'agency' ), Plan_Tier::ADMIN_TAB_PLANS );
			foreach ( array( 'admin/class-bbai-core.php', 'admin/traits/trait-core-admin-ui.php' ) as $rel ) {
				$src = Bbai_Template_Snippet::src( $rel );
				$this->assertStringContainsString( '$bbai_is_pro = \\BeepBeepAI\\AltTextGenerator\\Plan_Tier::can_see_admin_tab($bbai_plan_slug);', $src );
				$this->assertStringNotContainsString( '$bbai_is_pro = \\BeepBeepAI\\AltTextGenerator\\Plan_Tier::is_paid(', $src );
				$this->assertMatchesRegularExpression( "/if \\(\\\$bbai_is_pro\\) \\{\\s*\\\$bbai_allowed_tabs\\['admin'\\]/", $src );
			}
		}

		/* ---- 2. Autopilot CTAs ---- */

		/** @return array<string,mixed> */
		private function daily( string $ladder, bool $premium, int $limit, bool $has_upload, bool $live_autopilot = false ): array {
			$markers = array(
				'$bbai_daily_settings_autopilot_url =',
				'$bbai_daily_live_can_autopilot =',
				'$bbai_daily_live_quota_limit =',
				'$bbai_daily_is_pro_plan =',
				'$bbai_daily_plan_slug_ladder =',
				'$bbai_daily_plan_lacks_autopilot =',
				'$bbai_daily_can_autopilot =',
				'$bbai_daily_autopilot_action =',
				'$bbai_daily_upgrade = [',
			);
			$code = array();
			foreach ( $markers as $m ) {
				$code[] = Bbai_Template_Snippet::statement( self::PAGE, $m );
			}
			return Bbai_Template_Snippet::run(
				$code,
				array(
					'bbai_plan_slug_ladder'            => $ladder,
					'bbai_state_is_pro_plan'           => $premium,
					'bbai_is_premium'                  => $premium,
					'bbai_has_upload_generation'       => $has_upload,
					'bbai_usage_can_autopilot'         => $live_autopilot,
					'bbai_usage_stats'                 => array( 'limit' => $limit, 'can_autopilot' => $live_autopilot ),
					'bbai_li_state'                    => array(),
					'bbai_dashboard_root_credits_total' => $limit,
				)
			);
		}

		public function test_starter_gets_no_autopilot_cta_like_4_6_142(): void {
			$v = $this->daily( 'starter', true, 100, false );
			$this->assertFalse( $v['bbai_daily_can_autopilot'] );
			$this->assertSame( 'show-upgrade-modal', $v['bbai_daily_autopilot_action']['action'] );
			$this->assertSame( '#', $v['bbai_daily_autopilot_action']['href'] );
			$this->assertSame( 'Upgrade plan', $v['bbai_daily_upgrade']['label'] );
			$this->assertSame( 'show-upgrade-modal', $v['bbai_daily_upgrade']['action'] );
			$this->assertTrue( $v['bbai_daily_is_pro_plan'], 'Starter keeps the paid free-limit copy fix' );
		}

		public function test_starter_never_gets_autopilot_even_with_a_stale_autopilot_signal(): void {
			$v = $this->daily( 'starter', true, 1000, false, true );
			$this->assertFalse( $v['bbai_daily_can_autopilot'] );
			$this->assertSame( 'Upgrade plan', $v['bbai_daily_upgrade']['label'] );
		}

		public static function autopilot_plans(): array {
			// dashboard-body maps growth to 'pro' on the ladder.
			return array( 'growth' => array( 'pro', 1000 ), 'agency' => array( 'agency', 10000 ), 'enterprise' => array( 'enterprise', 10000 ) );
		}

		#[DataProvider( 'autopilot_plans' )]
		public function test_growth_and_agency_keep_autopilot_ctas( string $ladder, int $limit ): void {
			$v = $this->daily( $ladder, true, $limit, true );
			$this->assertTrue( $v['bbai_daily_can_autopilot'] );
			$this->assertSame( 'navigate', $v['bbai_daily_autopilot_action']['action'] );
			$this->assertStringContainsString( 'bbai-enable-on-upload', $v['bbai_daily_autopilot_action']['href'] );
			$this->assertSame( 'Manage Autopilot', $v['bbai_daily_upgrade']['label'] );
		}

		public function test_free_gets_upgrade_cta_and_free_limit_note(): void {
			$v = $this->daily( 'free', false, 25, false );
			$this->assertFalse( $v['bbai_daily_can_autopilot'] );
			$this->assertFalse( $v['bbai_daily_is_pro_plan'] );
			$this->assertSame( 'Upgrade plan', $v['bbai_daily_upgrade']['label'] );
		}

		public function test_free_limit_note_and_autopilot_sublabel_use_the_right_flags(): void {
			$src = Bbai_Template_Snippet::src( self::PAGE );
			$this->assertStringContainsString( '<?php if ( ! $bbai_daily_is_pro_plan ) : ?>', $src );
			$this->assertStringContainsString( "esc_html( \$bbai_daily_can_autopilot ? __( 'Manage automatic generation'", $src );
		}

		/* ---- 3. Automation and Growth copy (dashboard-body) ---- */

		/** @return array<string,mixed> */
		private function body( string $plan, string $ladder, int $total, int $used, array $usage_extra = array() ): array {
			$markers = array(
				'$bbai_format_percentage_label =',
				'$bbai_is_agency =',
				'$bbai_usage_can_autopilot =',
				'$bbai_is_premium =',
				'$bbai_skips_credit_lock =',
				'$bbai_plan_lacks_upload_generation =',
				'$bbai_has_upload_generation =',
				'$bbai_plan_label = $bbai_is_anonymous_trial',
				'$bbai_growth_capacity =',
				'$bbai_growth_usage_percent =',
				'$bbai_growth_usage_display =',
				'$bbai_growth_usage_line =',
			);
			$code = array();
			foreach ( $markers as $m ) {
				$code[] = Bbai_Template_Snippet::statement( self::BODY, $m );
			}
			return Bbai_Template_Snippet::run(
				$code,
				array(
					'bbai_is_guest_trial'     => false,
					'bbai_is_anonymous_trial' => false,
					'bbai_free_plan_offer'    => 25,
					'bbai_plan_slug_ladder'   => $ladder,
					'bbai_plan_data'          => array(
						'plan_slug' => $plan,
						'is_agency' => 'agency' === $plan,
						'is_pro'    => Plan_Tier::skips_limit_check( $plan ),
					),
					'bbai_usage_stats'        => array_merge( array( 'plan' => $plan ), $usage_extra ),
					'bbai_credits_total'      => $total,
					'bbai_credits_used'       => $used,
				)
			);
		}

		public function test_starter_gets_no_growth_capacity_line_and_keeps_paid_status(): void {
			$v = $this->body( 'starter', 'starter', 100, 40 );
			$this->assertTrue( $v['bbai_is_premium'], 'Starter keeps quota type paid' );
			$this->assertFalse( $v['bbai_has_upload_generation'] );
			$this->assertSame( 1000, $v['bbai_growth_capacity'] );
			$this->assertStringNotContainsString( 'Growth capacity', $v['bbai_growth_usage_line'] );
			$this->assertStringStartsWith( 'On Growth, this usage would be', $v['bbai_growth_usage_line'] );
		}

		public function test_starter_label_fallback_uses_the_plan_name_not_growth(): void {
			$v = $this->body( 'starter', 'starter', 100, 40 );
			$this->assertSame( 'Starter plan', $v['bbai_plan_label'] );
			$v = $this->body( 'starter', 'starter', 100, 40, array( 'can_autopilot' => true ) );
			$this->assertSame( 'Starter plan', $v['bbai_plan_label'], 'a stale autopilot flag does not relabel Starter' );
			$this->assertFalse( $v['bbai_has_upload_generation'] );
			$v = $this->body( 'starter', 'starter', 100, 40, array( 'plan_label' => 'Starter' ) );
			$this->assertSame( 'Starter', $v['bbai_plan_label'], 'backend label wins' );
		}

		public static function growth_copy_plans(): array {
			return array( 'growth' => array( 'growth', 'pro' ), 'pro' => array( 'pro', 'pro' ), 'agency' => array( 'agency', 'agency' ) );
		}

		#[DataProvider( 'growth_copy_plans' )]
		public function test_growth_and_agency_keep_growth_copy( string $plan, string $ladder ): void {
			$v = $this->body( $plan, $ladder, 1000, 100 );
			$this->assertTrue( $v['bbai_has_upload_generation'] );
			$this->assertStringContainsString( 'Growth capacity', $v['bbai_growth_usage_line'] );
			$this->assertSame( 'Growth plan', $v['bbai_plan_label'], '4.6.142 fallback label' );
		}

		public function test_free_label_and_line_unchanged(): void {
			$v = $this->body( 'free', 'free', 25, 5 );
			$this->assertFalse( $v['bbai_is_premium'] );
			$this->assertSame( 'Free plan', $v['bbai_plan_label'] );
			$this->assertStringStartsWith( 'On Growth, this usage would be', $v['bbai_growth_usage_line'] );
		}

		public function test_dashboard_body_automation_flags_are_wired(): void {
			$src = Bbai_Template_Snippet::src( self::BODY );
			$this->assertStringContainsString( "'canUseAutomation' => \$bbai_has_upload_generation,", $src );
			$this->assertStringContainsString( "'can_use_automation'     => \$bbai_has_upload_generation,", $src );
			$this->assertSame( 2, substr_count( $src, "data-bbai-can-automate=\"<?php echo esc_attr( \$bbai_has_upload_generation ? '1' : '0' ); ?>\"" ) );
		}

		/* ---- 3. Automation copy (Credit usage page) ---- */

		public static function usage_page_plans(): array {
			return array(
				'starter' => array( 'starter', false, true ), 'free' => array( 'free', false, false ),
				'growth' => array( 'growth', true, true ), 'pro' => array( 'pro', true, true ),
				'agency' => array( 'agency', true, true ), 'enterprise' => array( 'enterprise', true, true ),
			);
		}

		#[DataProvider( 'usage_page_plans' )]
		public function test_credit_usage_automation_copy( string $plan, bool $automation, bool $paid ): void {
			$rel  = 'admin/class-bbai-credit-usage-page.php';
			$code = array(
				Bbai_Template_Snippet::statement( $rel, '$is_pro_plan = Plan_Tier::is_paid' ),
				Bbai_Template_Snippet::statement( $rel, '$can_use_automation = ' ),
				Bbai_Template_Snippet::statement( $rel, '$automation_copy = ' ),
			);
			$v = Bbai_Template_Snippet::run( $code, array( 'plan_slug' => $plan ) );
			$this->assertSame( $paid, $v['is_pro_plan'] );
			$this->assertSame( $automation, $v['can_use_automation'] );
			$this->assertSame( $automation, str_starts_with( $v['automation_copy'], 'On-upload automation is available' ) );
		}

		public function test_credit_usage_supports_automation_copy_and_buttons_use_the_gate(): void {
			$src = Bbai_Template_Snippet::src( 'admin/class-bbai-credit-usage-page.php' );
			$this->assertMatchesRegularExpression( "/if \\( \\\$can_use_automation \\) \\{\\s*\\\$upgrade_context = __\\( 'Your current plan supports automation/", $src );
			$this->assertStringContainsString( "'canUseAutomation'       => \$can_use_automation,", $src );
			$this->assertStringContainsString( '} elseif ( ! $can_automate && ( $is_low || $usage_percent >= 75 ) ) {', $src );
			$tpl = Bbai_Template_Snippet::src( 'admin/partials/credit-usage-content.php' );
			$this->assertStringContainsString( '<?php if ($bbai_can_use_automation) : ?>', $tpl );
			$banner = Bbai_Template_Snippet::src( 'includes/admin/banner-system.php' );
			$this->assertStringContainsString( "if (!empty(\$s['can_use_automation'] ?? \$s['is_pro_plan'] ?? false)) {", $banner );
		}

		public function test_js_automation_cta_and_plan_label_use_the_gate(): void {
			$msg = Bbai_Template_Snippet::src( 'assets/js/bbai-banner-message.js' );
			$this->assertStringContainsString( ": canAutomate\n", $msg );
			$dash = Bbai_Template_Snippet::src( 'assets/js/bbai-dashboard.js' );
			$this->assertStringContainsString( "canAutomate: !!(data && (data.canAutomate != null ? data.canAutomate : data.isPremium)),", $dash );
			$this->assertStringContainsString( "__('Starter plan', 'beepbeep-ai-alt-text-generator')", $dash );
		}
	}
}
