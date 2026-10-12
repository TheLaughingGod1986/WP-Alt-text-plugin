<?php
/**
 * GET /api/usage must only run on Alt Text admin screens or Alt Text
 * AJAX/REST actions, be cached, and never run on the front end, cron or CLI.
 * Uses the real Usage_Tracker and the real API_Client_V2 class with a spy
 * subclass injected as the singleton, so no HTTP is ever made.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\API_Client_V2;
	use BeepBeepAI\AltTextGenerator\Usage_Tracker;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class UsageFetchGatingTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['bbai_test_transients'] = array();
			$GLOBALS['bbai_test_is_admin']   = false;
			$GLOBALS['bbai_test_usage_calls'] = 0;
			$GLOBALS['bbai_test_usage_reply'] = array( 'used' => 3, 'limit' => 25, 'remaining' => 22, 'plan' => 'free', 'plan_type' => 'free', 'source' => 'remote_usage' );
			$_GET = array();
			$_REQUEST = array();
			unset( $GLOBALS['pagenow'], $GLOBALS['wp'] );

			foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400 ) as $k => $v ) {
				if ( ! defined( $k ) ) {
					define( $k, $v );
				}
			}
			if ( ! function_exists( 'get_transient' ) ) {
				function get_transient( $key ) { return $GLOBALS['bbai_test_transients'][ $key ] ?? false; }
				function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bbai_test_transients'][ $key ] = $value; $GLOBALS['bbai_test_ttl'][ $key ] = $ttl; return true; }
				function delete_transient( $key ) { unset( $GLOBALS['bbai_test_transients'][ $key ] ); return true; }
			}
			if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return (bool) $GLOBALS['bbai_test_is_admin']; } }
			if ( ! function_exists( 'wp_doing_ajax' ) ) { function wp_doing_ajax() { return ! empty( $GLOBALS['bbai_test_ajax'] ); } }
			if ( ! function_exists( 'wp_doing_cron' ) ) { function wp_doing_cron() { return ! empty( $GLOBALS['bbai_test_cron'] ); } }
			if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
			if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ) ); } }
			if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); } }
			if ( ! function_exists( 'delete_option' ) ) { function delete_option( $key ) { unset( $GLOBALS['bbai_test_options'][ $key ] ); return true; } }
			if ( ! function_exists( 'wp_date' ) ) { function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); } }
			if ( ! function_exists( 'date_i18n' ) ) { function date_i18n( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); } }
			if ( ! function_exists( 'current_time' ) ) { function current_time( $t, $g = 0 ) { return 'timestamp' === $t ? time() : gmdate( 'Y-m-d H:i:s' ); } }
			if ( ! function_exists( 'wp_cache_delete' ) ) { function wp_cache_delete( $k, $g = '' ) { return true; } }
			if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return $v instanceof \WP_Error; } }
			if ( ! function_exists( '__' ) ) { function __( $t, $d = 'default' ) { return $t; } }
			if ( ! function_exists( 'bbai_is_authenticated' ) ) { function bbai_is_authenticated() { return true; } } // signed in, not a trial

			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			if ( ! class_exists( 'BBAI_Test_Usage_Spy_Client', false ) ) {
				eval(
					'class BBAI_Test_Usage_Spy_Client extends \BeepBeepAI\AltTextGenerator\API_Client_V2 {
						public function get_token() { return "jwt.test"; }
						public function get_license_key() { return ""; }
						public function has_active_license() { return false; }
						public function get_usage() { $GLOBALS["bbai_test_usage_calls"]++; return $GLOBALS["bbai_test_usage_reply"]; }
					}'
				);
			}
			$spy = ( new \ReflectionClass( 'BBAI_Test_Usage_Spy_Client' ) )->newInstanceWithoutConstructor();
			$ref = new \ReflectionProperty( API_Client_V2::class, 'instance' );
			$ref->setAccessible( true );
			$ref->setValue( null, $spy );
		}

		private function calls(): int {
			return (int) $GLOBALS['bbai_test_usage_calls'];
		}

		public function test_front_end_never_fetches(): void {
			$this->assertFalse( Usage_Tracker::remote_fetch_allowed() );
			Usage_Tracker::get_cached_usage();
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 0, $this->calls() );
		}

		public function test_cron_never_fetches_even_in_admin(): void {
			$GLOBALS['bbai_test_cron']     = true;
			$GLOBALS['bbai_test_is_admin'] = true;
			$_GET['page']                  = 'bbai';
			$this->assertFalse( Usage_Tracker::remote_fetch_allowed() );
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 0, $this->calls() );
		}

		public function test_cli_never_fetches(): void {
			define( 'WP_CLI', true );
			$GLOBALS['bbai_test_is_admin'] = true;
			$_GET['page']                  = 'bbai-settings';
			$this->assertFalse( Usage_Tracker::remote_fetch_allowed() );
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 0, $this->calls() );
		}

		public function test_other_admin_screens_do_not_fetch(): void {
			$GLOBALS['bbai_test_is_admin'] = true;
			$GLOBALS['pagenow']            = 'index.php';
			$this->assertFalse( Usage_Tracker::remote_fetch_allowed() );
			$_GET['page'] = 'some-other-plugin';
			$this->assertFalse( Usage_Tracker::remote_fetch_allowed() );
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 0, $this->calls() );
		}

		public function test_alt_text_screen_fetches_once_then_uses_cache(): void {
			$GLOBALS['bbai_test_is_admin'] = true;
			$_GET['page']                  = 'bbai';
			$this->assertTrue( Usage_Tracker::remote_fetch_allowed() );
			Usage_Tracker::get_cached_usage();
			Usage_Tracker::get_cached_usage();
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 1, $this->calls() );
			$this->assertNotFalse( get_transient( Usage_Tracker::CACHE_KEY ) );
			$this->assertSame( 300, $GLOBALS['bbai_test_ttl'][ Usage_Tracker::CACHE_KEY ] );
		}

		public function test_media_upload_screens_are_allowed(): void {
			$GLOBALS['bbai_test_is_admin'] = true;
			foreach ( array( 'upload.php', 'media-new.php', 'async-upload.php' ) as $script ) {
				$GLOBALS['pagenow'] = $script;
				$this->assertTrue( Usage_Tracker::remote_fetch_allowed(), $script );
			}
		}

		public function test_ajax_only_for_alt_text_actions(): void {
			$GLOBALS['bbai_test_ajax']     = true;
			$GLOBALS['bbai_test_is_admin'] = true;
			$_REQUEST['action']            = 'heartbeat';
			$this->assertFalse( Usage_Tracker::remote_fetch_allowed() );
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 0, $this->calls() );
			$_REQUEST['action'] = 'bbai_queue_stats';
			$this->assertTrue( Usage_Tracker::remote_fetch_allowed() );
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 1, $this->calls() );
		}

		public function test_rest_only_for_alt_text_routes(): void {
			define( 'REST_REQUEST', true );
			$GLOBALS['wp'] = (object) array( 'query_vars' => array( 'rest_route' => '/wp/v2/posts' ) );
			$this->assertFalse( Usage_Tracker::remote_fetch_allowed() );
			$GLOBALS['wp']->query_vars['rest_route'] = '/bbai/v1/usage';
			$this->assertTrue( Usage_Tracker::remote_fetch_allowed() );
		}

		public function test_failed_fetch_backs_off_instead_of_retrying_every_load(): void {
			$GLOBALS['bbai_test_is_admin']    = true;
			$_GET['page']                     = 'bbai';
			$GLOBALS['bbai_test_usage_reply'] = array( 'used' => 0, 'limit' => 0, 'source' => 'local_snapshot' ); // what get_usage() returns on an HTTP error
			Usage_Tracker::get_cached_usage();
			Usage_Tracker::get_cached_usage();
			$this->assertSame( 1, $this->calls() );
			$this->assertNotFalse( get_transient( Usage_Tracker::FETCH_BACKOFF_KEY ) );
			// An explicit refresh still goes through.
			Usage_Tracker::get_cached_usage( true );
			$this->assertSame( 2, $this->calls() );
		}

		public function test_explicit_refresh_is_honoured_anywhere(): void {
			Usage_Tracker::get_cached_usage( true );
			$this->assertSame( 1, $this->calls() );
		}
		/**
		 * One Alt Text screen load = what trait-core-assets enqueue (Usage_Helper)
		 * plus the upgrade modal template read. Both must share the cache/backoff.
		 */
		private function alt_text_screen_load(): void {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
			$client = API_Client_V2::get_instance();
			\BeepBeepAI\AltTextGenerator\Services\Usage_Helper::get_usage( $client, true );
			// templates/upgrade-modal.php reads usage this way now.
			Usage_Tracker::get_cached_usage( false );
		}

		public function test_screen_load_failing_backend_is_one_call_then_zero_within_backoff(): void {
			$GLOBALS['bbai_test_is_admin']    = true;
			$_GET['page']                     = 'bbai';
			$GLOBALS['bbai_test_usage_reply'] = array( 'used' => 0, 'limit' => 0, 'source' => 'local_snapshot' );
			$this->alt_text_screen_load();
			$this->assertSame( 1, $this->calls(), 'first load' );
			$this->alt_text_screen_load();
			$this->alt_text_screen_load();
			$this->assertSame( 1, $this->calls(), 'later loads within the backoff' );
			$this->assertSame( 300, $GLOBALS['bbai_test_ttl'][ Usage_Tracker::FETCH_BACKOFF_KEY ] );
		}

		public function test_screen_load_success_is_one_call_then_zero_within_cache(): void {
			$GLOBALS['bbai_test_is_admin'] = true;
			$_GET['page']                  = 'bbai';
			$this->alt_text_screen_load();
			$this->assertSame( 1, $this->calls(), 'first load' );
			$this->alt_text_screen_load();
			$this->alt_text_screen_load();
			$this->assertSame( 1, $this->calls(), 'later loads within the cache' );
		}

		public function test_screen_templates_never_call_get_usage_directly(): void {
			foreach ( array( 'templates/upgrade-modal.php', 'admin/partials/settings-tab.php', 'includes/services/class-usage-helper.php' ) as $f ) {
				$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . $f );
				$this->assertDoesNotMatchRegularExpression( '/->get_usage\(\)/', $src, $f );
			}
		}
	}
}
