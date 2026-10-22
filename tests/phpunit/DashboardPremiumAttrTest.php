<?php
/**
 * 4.6.143 (QA P1 on 56d972eb): data-bbai-is-premium on the dashboard roots is the
 * JS lock flag. At 0 credits every plan must render what 4.6.142.1 (c23a26d9)
 * rendered; Starter is "0" (any-paid would make it "1" and skip the JS lock).
 */
declare(strict_types=1);

namespace {
	require_once __DIR__ . '/stubs-template-snippets.php';
	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	}

	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\TestCase;

	final class DashboardPremiumAttrTest extends TestCase {
		private const BODY = 'admin/partials/dashboard-body.php';

		/** The two data-bbai-is-premium attributes, as PHP echo snippets. */
		private static function attribute_snippets( string $src ): array {
			preg_match_all( '/data-bbai-is-premium="<\?php (echo .*?;) \?>"/', $src, $m );
			return $m[1];
		}

		/** Inputs the template has at that point for a connected account at 0 credits. */
		private static function inputs( string $plan, bool $autopilot, bool $guest = false ): array {
			$ladder = $guest ? 'free' : $plan;
			if ( 'growth' === $ladder ) {
				$ladder = 'pro'; // dashboard-body.php ladder mapping.
			}
			return array(
				'bbai_is_guest_trial'   => $guest,
				'bbai_plan_slug_ladder' => $ladder,
				'bbai_plan_data'        => $guest ? array() : array(
					'plan_slug' => $plan,
					'is_agency' => 'agency' === $plan,
					// Plan_Helpers::get_plan_data() as on 4.6.142.1.
					'is_pro'    => ( 'pro' === $plan || 'agency' === $plan ),
				),
				'bbai_usage_stats'      => array( 'plan' => $plan, 'remaining' => 0, 'used' => 100, 'limit' => 100, 'can_autopilot' => $autopilot ),
			);
		}

		/** Render both attributes from the current template. */
		private static function render( array $vars ): array {
			$code = array();
			$src = Bbai_Template_Snippet::src( self::BODY );
			foreach ( array( '$bbai_is_agency =', '$bbai_usage_can_autopilot =', '$bbai_is_premium =', '$bbai_skips_credit_lock =', '$bbai_is_premium_attr =' ) as $mk ) {
				if ( false !== strpos( $src, $mk ) ) { // Render whatever the template defines.
					$code[] = Bbai_Template_Snippet::statement( self::BODY, $mk );
				}
			}
			$code[] = '$bbai_state_is_pro_plan = $bbai_is_premium;';
			$out = array();
			foreach ( self::attribute_snippets( Bbai_Template_Snippet::src( self::BODY ) ) as $echo ) {
				$out[] = Bbai_Template_Snippet::run( array_merge( $code, array( 'ob_start(); ' . $echo . ' $bbai_rendered = ob_get_clean();' ) ), $vars )['bbai_rendered'];
			}
			return $out;
		}

		/** Values 4.6.142.1 (c23a26d9) rendered at 0 credits. */
		public static function plans(): array {
			return array(
				'free'                 => array( 'free', false, '0' ),
				'trial'                => array( 'trial', false, '0' ),
				'starter'              => array( 'starter', false, '0' ),
				'starter + autopilot'  => array( 'starter', true, '1' ),
				'growth'               => array( 'growth', false, '1' ),
				'pro'                  => array( 'pro', false, '1' ),
				'agency'               => array( 'agency', false, '1' ),
				'enterprise'           => array( 'enterprise', false, '0' ),
				'enterprise + autopilot' => array( 'enterprise', true, '1' ),
			);
		}

		#[DataProvider( 'plans' )]
		public function test_attribute_matches_4_6_142_1_at_zero_credits( string $plan, bool $autopilot, string $expected ): void {
			$rendered = self::render( self::inputs( $plan, $autopilot ) );
			$this->assertCount( 2, $rendered, 'both dashboard roots carry the attribute' );
			$this->assertSame( array( $expected, $expected ), $rendered, "$plan" );
		}

		public function test_guest_renders_zero(): void {
			$this->assertSame( array( '0', '0' ), self::render( self::inputs( 'pro', true, true ) ) );
		}

		/** Cross-check against the 4.6.142.1 template itself when the git object is available. */
		#[DataProvider( 'plans' )]
		public function test_attribute_equals_c23a26d9_expression( string $plan, bool $autopilot, string $expected ): void {
			$old = @shell_exec( 'git -C ' . escapeshellarg( BEEPBEEP_AI_PLUGIN_DIR ) . ' show c23a26d9:admin/partials/dashboard-body.php 2>/dev/null' );
			if ( ! is_string( $old ) || '' === $old ) {
				$this->markTestSkipped( 'c23a26d9 not available' );
			}
			$code = array();
			foreach ( array( '$bbai_is_agency =', '$bbai_usage_can_autopilot =', '$bbai_is_premium =' ) as $mk ) {
				$pos    = strpos( $old, $mk );
				$this->assertNotFalse( $pos );
				$code[] = substr( $old, $pos, strpos( $old, ";\n", $pos ) - $pos + 1 );
			}
			$code[] = '$bbai_state_is_pro_plan = $bbai_is_premium;';
			$snips  = self::attribute_snippets( $old );
			$this->assertCount( 2, $snips );
			$old_out = array();
			foreach ( $snips as $echo ) {
				$old_out[] = Bbai_Template_Snippet::run( array_merge( $code, array( 'ob_start(); ' . $echo . ' $bbai_rendered = ob_get_clean();' ) ), self::inputs( $plan, $autopilot ) )['bbai_rendered'];
			}
			$this->assertSame( array( $expected, $expected ), $old_out, '4.6.142.1 value' );
			$this->assertSame( $old_out, self::render( self::inputs( $plan, $autopilot ) ) );
		}
	}
}
