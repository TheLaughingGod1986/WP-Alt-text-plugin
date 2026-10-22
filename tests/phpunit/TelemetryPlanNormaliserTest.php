<?php
/**
 * Checkout / upgrade events must keep `plan` as the account's current plan and keep
 * the plan being bought in `target_plan` (a Free user buying Starter is not `pro`).
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
use PHPUnit\Framework\TestCase;

/**
 * Runs in its own process so the real telemetry class is used (other tests stub it)
 * and these WordPress stubs never leak into other tests.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class TelemetryPlanNormaliserTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
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
		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return 0;
			}
		}
		if ( ! function_exists( 'get_user_meta' ) ) {
			function get_user_meta( $user_id, $key = '', $single = false ) {
				return '';
			}
		}
		if ( ! function_exists( 'get_transient' ) ) {
			function get_transient( $key ) {
				return false;
			}
		}
		if ( ! function_exists( 'wp_parse_url' ) ) {
			function wp_parse_url( $url, $component = -1 ) {
				return parse_url( $url, $component );
			}
		}
		if ( ! function_exists( 'esc_url_raw' ) ) {
			function esc_url_raw( $url ) {
				return (string) $url;
			}
		}
		if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
		}
	}

	private function enrich( array $props, string $event ): array {
		$method = new ReflectionMethod( BBAI_Telemetry::class, 'with_site_identity' );
		$method->setAccessible( true );
		return $method->invoke( null, $props, 'site_test', $event );
	}

	public function test_free_user_checkout_for_starter_keeps_free_plan(): void {
		$out = $this->enrich(
			array(
				'plan'         => 'starter',
				'current_plan' => 'free',
				'target_plan'  => 'starter',
			),
			'checkout_started'
		);
		$this->assertSame( 'free', $out['plan'] );
		$this->assertSame( 'free', $out['plan_type'] );
		$this->assertSame( 'starter', $out['target_plan'] );
	}

	public function test_target_plan_is_backfilled_from_plan_on_checkout_events(): void {
		$out = $this->enrich(
			array(
				'plan'         => 'growth',
				'current_plan' => 'free',
			),
			'checkout_fallback_payment_link'
		);
		$this->assertSame( 'free', $out['plan'] );
		$this->assertSame( 'growth', $out['target_plan'] );
	}

	public function test_non_checkout_events_keep_existing_bucket(): void {
		$out = $this->enrich( array( 'plan' => 'starter' ), 'alt_generated' );
		// A Starter account reports starter (QA 4.6.142: it used to be bucketed as pro).
		$this->assertSame( 'starter', $out['plan'] );
		$this->assertArrayNotHasKey( 'target_plan', $out );
	}
}
