<?php
/**
 * Signed-in usage must follow the backend, not a local monotonic floor.
 */
declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator {
	if ( ! class_exists( __NAMESPACE__ . '\\API_Client_V2', false ) ) {
		// Minimal fake client; only credential checks are needed here.
		class API_Client_V2 {
			public static function get_instance() { return new self(); }
			public function get_token() { return $GLOBALS['bbai_test_token'] ?? ''; }
			public function get_license_key() { return ''; }
			public function has_active_license() { return false; }
		}
	}
}

namespace {
	use BeepBeepAI\AltTextGenerator\Usage_Tracker;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class UsageTrustBackendTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['bbai_test_transients'] = array();
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
				function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }
			}
			if ( ! function_exists( 'wp_cache_delete' ) ) {
				function wp_cache_delete( $key, $group = '' ) { return true; }
			}
			if ( ! function_exists( 'sanitize_key' ) ) {
				function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ) ); }
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		}

		private function floor_key(): string {
			return 'bbai_generation_success_floor_' . gmdate( 'Ym' );
		}

		private function live( int $used, int $limit = 25 ): array {
			return array( 'used' => $used, 'limit' => $limit, 'remaining' => $limit - $used, 'source' => 'remote_usage' );
		}

		public function test_signed_in_backend_lower_count_wins_and_floor_is_deleted(): void {
			$GLOBALS['bbai_test_token'] = 'jwt';
			update_option( $this->floor_key(), 25 );
			set_transient( Usage_Tracker::CACHE_KEY, array( 'used' => 24, 'limit' => 25, 'remaining' => 1, 'source' => 'generation_success' ) );

			$this->assertNull( Usage_Tracker::get_newer_generation_success_snapshot( $this->live( 15 ) ) );
			$this->assertFalse( get_option( $this->floor_key(), false ) );
		}

		public function test_stale_floor_option_is_ignored_even_without_credentials(): void {
			$GLOBALS['bbai_test_token'] = '';
			update_option( $this->floor_key(), 25 );
			// No cached local generation snapshot: the old floor alone must not override.
			$this->assertNull( Usage_Tracker::get_newer_generation_success_snapshot( $this->live( 10 ) ) );
		}

		public function test_not_signed_in_keeps_newer_local_snapshot_behaviour(): void {
			$GLOBALS['bbai_test_token'] = '';
			set_transient( Usage_Tracker::CACHE_KEY, array( 'used' => 5, 'limit' => 25, 'remaining' => 20, 'source' => 'generation_success' ) );
			$out = Usage_Tracker::get_newer_generation_success_snapshot( $this->live( 3 ) );
			$this->assertIsArray( $out );
			$this->assertSame( 5, (int) $out['used'] );
		}

		public function test_upgrade_purge_runs_once(): void {
			update_option( $this->floor_key(), 25 );
			Usage_Tracker::maybe_purge_generation_success_floors();
			$this->assertFalse( get_option( $this->floor_key(), false ) );
			$this->assertSame( '1', get_option( 'bbai_generation_floor_retired' ) );
		}
	}
}
