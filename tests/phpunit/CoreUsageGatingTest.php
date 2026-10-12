<?php
/**
 * The real Core must not call GET /api/usage on a normal front-end, cron or
 * CLI load. Before the fix, Core::__construct() -> ensure_upload_generation_default()
 * -> current_account_can_use_upload_generation() called it on every load for
 * signed-in non-paid accounts, bypassing the usage transient.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\API_Client_V2;
	use BeepBeepAI\AltTextGenerator\Core;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class CoreUsageGatingTest extends TestCase {
		/** @var Core */
		private $core;

		protected function setUp(): void {
			$GLOBALS['bbai_test_transients']  = array();
			$GLOBALS['bbai_test_is_admin']    = false;
			$GLOBALS['bbai_test_usage_calls'] = 0;
			$_GET                             = array();
			if ( ! function_exists( 'get_transient' ) ) {
				function get_transient( $key ) { return $GLOBALS['bbai_test_transients'][ $key ] ?? false; }
				function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bbai_test_transients'][ $key ] = $value; return true; }
				function delete_transient( $key ) { unset( $GLOBALS['bbai_test_transients'][ $key ] ); return true; }
			}
			if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return (bool) $GLOBALS['bbai_test_is_admin']; } }
			if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
			if ( ! function_exists( 'wp_date' ) ) { function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); } }
			if ( ! function_exists( 'date_i18n' ) ) { function date_i18n( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); } }
			if ( ! function_exists( 'bbai_is_authenticated' ) ) { function bbai_is_authenticated() { return true; } }
			require __DIR__ . '/stubs-process-queue.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';

			if ( ! class_exists( 'BBAI_Test_Core_Usage_Spy', false ) ) {
				eval(
					'class BBAI_Test_Core_Usage_Spy extends \BeepBeepAI\AltTextGenerator\API_Client_V2 {
						public function get_token() { return "jwt.test"; }
						public function get_license_key() { return ""; }
						public function has_active_license() { return false; }
						public function get_license_data() { return null; }
						public function is_authenticated() { return true; }
						public function get_usage() { $GLOBALS["bbai_test_usage_calls"]++; return array( "used" => 1, "limit" => 25, "plan" => "free", "plan_type" => "free", "source" => "remote_usage" ); }
					}'
				);
			}
			$spy  = ( new \ReflectionClass( 'BBAI_Test_Core_Usage_Spy' ) )->newInstanceWithoutConstructor();
			$inst = new \ReflectionProperty( API_Client_V2::class, 'instance' );
			$inst->setAccessible( true );
			$inst->setValue( null, $spy );

			$ref        = new \ReflectionClass( Core::class );
			$this->core = $ref->newInstanceWithoutConstructor();
			$prop       = $ref->getProperty( 'api_client' );
			$prop->setAccessible( true );
			$prop->setValue( $this->core, $spy );
		}

		private function can_use_upload_generation(): bool {
			$m = new \ReflectionMethod( Core::class, 'current_account_can_use_upload_generation' );
			$m->setAccessible( true );
			return (bool) $m->invoke( $this->core );
		}

		public function test_front_end_load_makes_no_usage_call(): void {
			$this->assertFalse( $this->can_use_upload_generation() );
			$this->assertFalse( $this->can_use_upload_generation() );
			$this->assertSame( 0, $GLOBALS['bbai_test_usage_calls'] );
		}

		public function test_alt_text_screen_calls_usage_once_then_caches(): void {
			$GLOBALS['bbai_test_is_admin'] = true;
			$_GET['page']                  = 'bbai';
			$this->can_use_upload_generation();
			$this->can_use_upload_generation();
			$this->can_use_upload_generation();
			$this->assertSame( 1, $GLOBALS['bbai_test_usage_calls'], 'one fetch, then the transient' );
		}

		public function test_constructor_skips_plan_check_outside_alt_text_screens(): void {
			$src  = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php' );
			$ctor = substr( $src, (int) strpos( $src, 'public function __construct()' ), 4000 );
			$this->assertMatchesRegularExpression( '/if \(\\\\BeepBeepAI\\\\AltTextGenerator\\\\Usage_Tracker::remote_fetch_allowed\(\)\) \{\s*\$this->ensure_upload_generation_default\(\);/', $ctor );
		}
	}
}
