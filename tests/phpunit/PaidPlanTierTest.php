<?php
/**
 * 4.6.143: every paid plan (Starter included) counts as paid, through one helper.
 */
declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator {
	if ( ! class_exists( __NAMESPACE__ . '\\API_Client_V2', false ) ) {
		class API_Client_V2 {
			public static function get_instance() { return new self(); }
			public function get_token() { return $GLOBALS['bbai_test_token'] ?? ''; }
			public function get_license_key() { return ''; }
			public function has_active_license() { return false; }
		}
	}
}

namespace {
	use BeepBeepAI\AltTextGenerator\Plan_Tier;
	use BeepBeepAI\AltTextGenerator\Usage_Tracker;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class PaidPlanTierTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['bbai_test_transients'] = array();
			$GLOBALS['bbai_test_token']      = 'jwt';
			if ( ! function_exists( 'delete_option' ) ) {
				function delete_option( $key ) { unset( $GLOBALS['bbai_test_options'][ $key ] ); return true; }
			}
			if ( ! function_exists( 'get_transient' ) ) {
				function get_transient( $key ) { return $GLOBALS['bbai_test_transients'][ $key ] ?? false; }
				function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bbai_test_transients'][ $key ] = $value; return true; }
				function delete_transient( $key ) { unset( $GLOBALS['bbai_test_transients'][ $key ] ); return true; }
			}
			if ( ! function_exists( 'wp_date' ) ) {
				function wp_date( $format ) { return gmdate( $format ); }
			}
			if ( ! function_exists( 'date_i18n' ) ) {
				function date_i18n( $format, $ts = null ) { return gmdate( $format, $ts ?? time() ); }
			}
			if ( ! function_exists( 'current_time' ) ) {
				function current_time( $type ) { return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' ); }
			}
			if ( ! function_exists( 'wp_cache_delete' ) ) {
				function wp_cache_delete( $key, $group = '' ) { return true; }
			}
			if ( ! function_exists( 'sanitize_key' ) ) {
				function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ) ); }
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		}

		public static function plans(): array {
			return array(
				'starter'         => array( 'starter', true ),
				'Starter (case)'  => array( 'Starter', true ),
				'growth'          => array( 'growth', true ),
				'pro'             => array( 'pro', true ),
				'agency'          => array( 'agency', true ),
				'enterprise'      => array( 'enterprise', true ),
				'free'            => array( 'free', false ),
				'trial'           => array( 'trial', false ),
				'anonymous_trial' => array( 'anonymous_trial', false ),
				'credits'         => array( 'credits', false ),
				'empty'           => array( '', false ),
				'null'            => array( null, false ),
			);
		}

		#[DataProvider( 'plans' )]
		public function test_is_paid( $plan, bool $paid ): void {
			$this->assertSame( $paid, Plan_Tier::is_paid( $plan ) );
		}

		public function test_usage_tracker_keeps_starter_as_paid_starter(): void {
			Usage_Tracker::update_usage( array( 'used' => 3, 'limit' => 100, 'remaining' => 97, 'plan' => 'starter' ) );
			$cached = get_transient( Usage_Tracker::CACHE_KEY );
			$this->assertIsArray( $cached );
			$this->assertSame( 'starter', $cached['plan'] ?? $cached['plan_type'] ?? null, 'Starter must not be normalised to free' );
			$this->assertSame( 'paid', $cached['quota_type'] ?? null );
		}

		/**
		 * The QA P1 sites and the other plan checks found by the 4.6.143 grep
		 * must use the shared helper, not a pro/agency-only list.
		 */
		public function test_paid_checks_use_the_shared_helper(): void {
			$root  = BEEPBEEP_AI_PLUGIN_DIR;
			$files = array(
				'admin/partials/dashboard-logged-in-page.php',
				'admin/partials/dashboard-body.php',
				'admin/partials/library-tab.php',
				'includes/class-usage-tracker.php',
				'includes/admin/class-plan-helpers.php',
				'includes/traits/trait-api-usage.php',
				'admin/traits/trait-core-admin-ui.php',
				'admin/class-bbai-rest-controller.php',
				'admin/class-bbai-credit-usage-page.php',
				'includes/services/class-usage-helper.php',
				'includes/services/class-logged-in-dashboard-resolver.php',
			);
			foreach ( $files as $file ) {
				$src = (string) file_get_contents( $root . $file );
				$this->assertStringContainsString( 'Plan_Tier::', $src, $file );
				$this->assertDoesNotMatchRegularExpression( "/\\[\\s*'pro',\\s*'agency'\\s*\\]/", $src, $file );
				$this->assertDoesNotMatchRegularExpression( "/'pro'\\s*\\|\\|\\s*\\\$\\w+\\s*===\\s*'agency'|=== 'pro' \\|\\| \\\$\\w+ === 'agency'/", $src, $file );
				$this->assertDoesNotMatchRegularExpression( "/\\[\\s*'pro',\\s*'growth',\\s*'agency'(,\\s*'enterprise')?\\s*\\]/", $src, $file );
			}
		}
	}
}
