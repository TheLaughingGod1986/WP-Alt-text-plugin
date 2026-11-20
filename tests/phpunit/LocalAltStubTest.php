<?php
/**
 * The local text-only ALT stub is a developer switch only: never turned on
 * from the request host (localhost / 127.0.0.1), only when
 * BEEPBEEP_AI_LOCAL_ALT_STUB is defined true. Stub output records no
 * alt_generated event and no first-success marker.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Core;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class LocalAltStubTest extends TestCase {
		/** Run the main file's constant block exactly as shipped. */
		private function run_main_file_stub_block(): void {
			$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'beepbeep-ai-alt-text-generator.php' );
			$start = strpos( $src, "if ( ! defined( 'BEEPBEEP_AI_LOCAL_ALT_STUB' ) ) {" );
			$this->assertNotFalse( $start );
			$end   = strpos( $src, "\n}\n", $start ) + 3;
			eval( substr( $src, $start, $end - $start ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		private function core(): Core {
			require __DIR__ . '/stubs-process-queue.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
			return ( new \ReflectionClass( Core::class ) )->newInstanceWithoutConstructor();
		}

		public function test_localhost_host_does_not_enable_stub(): void {
			$_SERVER['HTTP_HOST'] = 'localhost:8888';
			$this->run_main_file_stub_block();
			$this->assertTrue( defined( 'BEEPBEEP_AI_LOCAL_ALT_STUB' ) );
			$this->assertFalse( BEEPBEEP_AI_LOCAL_ALT_STUB );
			$this->assertFalse( $this->core()->is_local_alt_stub_active() );
		}

		public function test_loopback_ip_does_not_enable_stub(): void {
			$_SERVER['HTTP_HOST'] = '127.0.0.1';
			$this->run_main_file_stub_block();
			$this->assertFalse( BEEPBEEP_AI_LOCAL_ALT_STUB );
			$this->assertFalse( $this->core()->is_local_alt_stub_active() );
		}

		public function test_constant_true_enables_stub(): void {
			$_SERVER['HTTP_HOST'] = 'example.com';
			define( 'BEEPBEEP_AI_LOCAL_ALT_STUB', true );
			$this->run_main_file_stub_block();
			$this->assertTrue( $this->core()->is_local_alt_stub_active() );
		}

		public function test_truthy_non_true_constant_does_not_enable_stub(): void {
			define( 'BEEPBEEP_AI_LOCAL_ALT_STUB', 1 );
			$this->assertFalse( $this->core()->is_local_alt_stub_active() );
		}

		public function test_explicit_backend_url_wins_over_stub(): void {
			define( 'BEEPBEEP_AI_LOCAL_ALT_STUB', true );
			define( 'BEEPBEEP_AI_API_URL', 'https://api.example.invalid' );
			$this->assertFalse( $this->core()->is_local_alt_stub_active() );
		}

		public function test_no_host_sniffing_left_in_main_file(): void {
			$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'beepbeep-ai-alt-text-generator.php' );
			$this->assertStringNotContainsString( 'bbai_stub_host', $src );
			$this->assertStringNotContainsString( "localhost|127\\.0\\.0\\.1", $src );
		}

		public function test_stub_branch_records_no_first_success_or_event(): void {
			$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php' );
			$start = strpos( $src, 'if ( $local_or_dev_stub ) {' );
			$this->assertNotFalse( $start );
			$end  = strpos( $src, 'return $stub_alt;', $start );
			$body = substr( $src, $start, $end - $start );
			$this->assertStringContainsString( 'persist_generation_result(', $body );
			$this->assertStringNotContainsString( 'record_generation_success', $body );
			$this->assertStringNotContainsString( 'maybe_emit_alt_generated_event', $body );
			$this->assertStringNotContainsString( 'mark_first_success', $body );
			// The ajax first_alt_generated emit skips stub runs.
			$this->assertStringContainsString( '! $this->is_local_alt_stub_active() && ! get_user_meta( $uid, \'bbai_telemetry_first_alt_at\', true )', $src );
		}
	}
}
