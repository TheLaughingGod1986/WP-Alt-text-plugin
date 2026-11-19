<?php
/**
 * uninstall.php removes the opt-in lifecycle marker, so a reinstall that opts
 * in again sends its own snapshot.
 */
declare(strict_types=1);

namespace {
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class UninstallTelemetryOptionsTest extends TestCase {
		public function test_uninstall_deletes_opt_in_lifecycle_sent_option(): void {
			$GLOBALS['bbai_test_deleted_options'] = array();
			if ( ! function_exists( 'delete_option' ) ) {
				function delete_option( $key ) {
					$GLOBALS['bbai_test_deleted_options'][] = $key;
					unset( $GLOBALS['bbai_test_options'][ $key ] );
					return true;
				}
			}
			foreach ( array( 'delete_transient', 'wp_clear_scheduled_hook', 'delete_metadata' ) as $fn ) {
				if ( ! function_exists( $fn ) ) {
					eval( "function {$fn}( ...\$a ) { return true; }" );
				}
			}
			if ( ! function_exists( 'get_role' ) ) {
				function get_role( $r ) {
					return null;
				}
			}
			if ( ! function_exists( 'esc_sql' ) ) {
				function esc_sql( $v ) {
					return $v;
				}
			}
			$GLOBALS['wpdb'] = new class() {
				public $prefix   = 'wp_';
				public $postmeta = 'wp_postmeta';
				public function prepare( $q, ...$a ) { return $q; }
				public function query( $q ) { return 0; }
			};
			if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
				define( 'WP_UNINSTALL_PLUGIN', 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' );
			}
			$GLOBALS['bbai_test_options']['bbai_telemetry_opt_in_lifecycle_sent'] = '2026-10-29T11:39:45+00:00';

			( static function () {
				global $wpdb;
				require BEEPBEEP_AI_PLUGIN_DIR . 'uninstall.php';
			} )();

			$this->assertContains( 'bbai_telemetry_opt_in_lifecycle_sent', $GLOBALS['bbai_test_deleted_options'] );
			$this->assertArrayNotHasKey( 'bbai_telemetry_opt_in_lifecycle_sent', $GLOBALS['bbai_test_options'] );
			$this->assertContains( 'bbai_telemetry_consent', $GLOBALS['bbai_test_deleted_options'] );
		}
	}
}
