<?php
/**
 * P0 regression (live 4.6.142): minimum_monthly_limit_for_plan() read
 * Usage_Tracker::MIN_PAID_MONTHLY_LIMIT, which does not exist (it lives on
 * API_Client_V2), so every plugin admin page fataled for Pro/Growth/Agency/Enterprise.
 */
declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator {
	// Test doubles for the data sources the admin asset path reads. Only defined
	// here; each test runs in its own process so they never leak into other suites.
	if ( ! class_exists( __NAMESPACE__ . '\\Usage_Tracker', false ) ) {
		class Usage_Tracker {
			const CACHE_KEY = 'bbai_usage_cache';
			public static function get_stats_display( $force = false ) { return $GLOBALS['bbai_test_usage'] ?? array(); }
			public static function get_upgrade_url() { return 'https://example.com/upgrade'; }
			public static function get_billing_portal_url() { return ''; }
		}
	}
	if ( ! class_exists( __NAMESPACE__ . '\\Auth_State', false ) ) {
		class Auth_State {
			public static function resolve( $api_client ): array {
				return array( 'has_connected_account' => true, 'is_logged_in' => true );
			}
		}
	}
	if ( ! class_exists( __NAMESPACE__ . '\\BBAI_Telemetry', false ) ) {
		class BBAI_Telemetry {
			const TELEMETRY_SCHEMA_VERSION = '1';
			public static function normalize_plan_value( $v ) { return strtolower( (string) $v ); }
			public static function get_plugin_slug() { return 'beepbeep-ai-alt-text-generator'; }
			public static function get_posthog_api_host() { return ''; }
			public static function get_posthog_api_key() { return ''; }
			public static function has_telemetry_consent() { return false; }
			public static function inactive_days_at_session_start() { return 0; }
			public static function touch_last_active() {}
		}
	}
}

namespace BeepBeepAI\AltTextGenerator\Services {
	if ( ! class_exists( __NAMESPACE__ . '\\Usage_Helper', false ) ) {
		class Usage_Helper {
			public static function get_usage( $api_client, bool $has_connected_account = false, bool $force_refresh = false ): array {
				return $GLOBALS['bbai_test_usage'] ?? array();
			}
		}
	}
}

namespace {
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class MinPaidMonthlyLimitTest extends TestCase {
		protected function setUp(): void {
			require_once __DIR__ . '/stubs-admin-assets.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-assets.php';
			require_once __DIR__ . '/fixtures-core-assets-host.php';
		}

		public static function plans(): array {
			return array(
				'growth'     => array( 'growth', 1000 ),
				'agency'     => array( 'agency', 1000 ),
				'pro'        => array( 'pro', 1000 ),
				'enterprise' => array( 'enterprise', 1000 ),
				'starter'    => array( 'starter', 100 ),
				'free'       => array( 'free', 0 ),
			);
		}

		#[DataProvider( 'plans' )]
		public function test_minimum_monthly_limit_for_plan( string $plan, int $expected ): void {
			$host   = new \BBAI_Test_Core_Assets_Host();
			$method = new \ReflectionMethod( $host, 'minimum_monthly_limit_for_plan' );
			$method->setAccessible( true );
			$this->assertSame( $expected, $method->invoke( $host, $plan ) );
		}

		public static function paid_admin_plans(): array {
			return array(
				'growth' => array( 'growth', 1000 ),
				'agency' => array( 'agency', 10000 ),
			);
		}

		/**
		 * Drives the real admin_enqueue_scripts entry point for the plugin's own
		 * admin page (enqueue_admin -> enqueue_media_library_assets /
		 * enqueue_dashboard_assets -> get_initial_entitlement_state ->
		 * minimum_monthly_limit_for_plan) as a signed-in paid account.
		 */
		#[DataProvider( 'paid_admin_plans' )]
		public function test_admin_page_assets_load_for_paid_plan( string $plan, int $limit ): void {
			$GLOBALS['bbai_test_usage'] = array(
				'plan'      => $plan,
				'plan_type' => $plan,
				'limit'     => $limit,
				'used'      => 10,
				'remaining' => $limit - 10,
			);
			$_GET['page'] = 'bbai';

			$host = new \BBAI_Test_Core_Assets_Host();
			$host->enqueue_admin( 'toplevel_page_bbai' );

			$localized = $GLOBALS['bbai_test_localized'] ?? array();
			$this->assertArrayHasKey( 'BBAI', $localized, 'admin page path did not reach the BBAI localisation' );
			$this->assertArrayHasKey( 'entitlementState', $localized['BBAI'] );
			$state = $localized['BBAI']['entitlementState'];
			$this->assertSame( $plan, $state['plan'] );
			$this->assertTrue( $state['can_generate'] );
			$this->assertTrue( $state['can_autopilot'] );
		}
	}
}
