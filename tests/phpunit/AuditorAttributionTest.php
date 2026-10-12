<?php
/**
 * Website Auditor deep-link: args survive the signed-out redirect, the
 * tracking event fires once, and sign-in lands once on the library view.
 */
declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator {
	if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
		class BBAI_Telemetry {
			public static function emit( string $event_name, array $properties = array() ): void {
				$GLOBALS['bbai_test_events'][] = array( $event_name, $properties );
			}
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use function BeepBeepAI\AltTextGenerator\consume_auditor_landing;
	use function BeepBeepAI\AltTextGenerator\get_auditor_attribution;
	use function BeepBeepAI\AltTextGenerator\mark_auditor_landing_pending;
	use function BeepBeepAI\AltTextGenerator\remember_auditor_attribution;
	use function BeepBeepAI\AltTextGenerator\with_auditor_args;

	final class AuditorAttributionTest extends TestCase {
		protected function setUp(): void {
			// Stubs are declared lazily so other test files' unguarded stubs load first.
			if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }
			if ( ! function_exists( 'get_transient' ) ) { function get_transient( $key ) { return $GLOBALS['bbai_test_transients'][ $key ] ?? false; } }
			if ( ! function_exists( 'set_transient' ) ) { function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['bbai_test_transients'][ $key ] = $value; return true; } }
			if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ) ); } }
			if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
			if ( ! function_exists( 'add_action' ) ) { function add_action( ...$a ) { return true; } }
			if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return true; } }
			if ( ! function_exists( 'wp_doing_ajax' ) ) { function wp_doing_ajax() { return false; } }
			if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return (int) ( $GLOBALS['bbai_test_user_id'] ?? 0 ); } }
			if ( ! function_exists( 'admin_url' ) ) { function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; } }
			if ( ! function_exists( 'add_query_arg' ) ) {
			function add_query_arg( array $args, string $url ) {
			$parts = explode( '?', $url, 2 );
			$q     = array();
			if ( isset( $parts[1] ) ) { parse_str( $parts[1], $q ); }
			return $parts[0] . '?' . http_build_query( array_merge( $q, $args ) );
			}
			}

			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-auditor-attribution.php';
			$GLOBALS['bbai_test_transients'] = array();
			$GLOBALS['bbai_test_events']     = array();
			$GLOBALS['bbai_test_user_id']    = 7;
			$_GET = $_REQUEST = array();
		}

		private function visit( array $get ): void {
			$_GET = $_REQUEST = $get;
			remember_auditor_attribution();
		}

		private function events(): array {
			return array_values( array_filter( $GLOBALS['bbai_test_events'], fn( $e ) => 'auditor_deeplink_opened' === $e[0] ) );
		}

		public function test_signed_out_redirect_keeps_source_and_finding(): void {
			$_GET = $_REQUEST = array( 'page' => 'bbai-library', 'source' => 'auditor', 'finding' => 'missing_alt' );
			$url  = with_auditor_args( 'https://example.com/wp-admin/admin.php?page=bbai&upgrade=required' );
			parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
			$this->assertSame( 'bbai', $q['page'] );
			$this->assertSame( 'auditor', $q['source'] );
			$this->assertSame( 'missing_alt', $q['finding'] );
			$this->assertSame( 'required', $q['upgrade'] );
		}

		public function test_redirect_untouched_without_auditor_source(): void {
			$_GET = $_REQUEST = array( 'page' => 'bbai-library', 'source' => 'email' );
			$this->assertSame( 'https://example.com/wp-admin/admin.php?page=bbai', with_auditor_args( 'https://example.com/wp-admin/admin.php?page=bbai' ) );
		}

		public function test_event_fires_once_across_redirect_and_reloads(): void {
			$this->visit( array( 'page' => 'bbai-library', 'source' => 'auditor', 'finding' => 'missing_alt' ) );
			$this->visit( array( 'page' => 'bbai', 'source' => 'auditor', 'finding' => 'missing_alt', 'upgrade' => 'required' ) );
			$this->visit( array( 'page' => 'bbai', 'source' => 'auditor', 'finding' => 'missing_alt' ) );
			$events = $this->events();
			$this->assertCount( 1, $events );
			$this->assertSame( 'auditor', $events[0][1]['source'] );
			$this->assertSame( 'missing_alt', $events[0][1]['finding'] );
		}

		public function test_no_event_without_auditor_source_or_user(): void {
			$this->visit( array( 'page' => 'bbai' ) );
			$GLOBALS['bbai_test_user_id'] = 0;
			$this->visit( array( 'page' => 'bbai', 'source' => 'auditor', 'finding' => 'missing_alt' ) );
			$this->assertCount( 0, $this->events() );
		}

		public function test_attribution_survives_for_generation_without_request_args(): void {
			$this->visit( array( 'page' => 'bbai-library', 'source' => 'auditor', 'finding' => 'missing_alt' ) );
			$_GET = $_REQUEST = array();
			$this->assertSame( array( 'source' => 'auditor', 'finding' => 'missing_alt' ), get_auditor_attribution() );
		}

		public function test_sign_in_lands_once_on_missing_library(): void {
			$this->visit( array( 'page' => 'bbai-library', 'source' => 'auditor', 'finding' => 'missing_alt' ) );
			$this->assertNull( consume_auditor_landing(), 'No landing before the guest guard marks it.' );
			mark_auditor_landing_pending();
			$url = consume_auditor_landing();
			$this->assertNotNull( $url );
			parse_str( (string) parse_url( (string) $url, PHP_URL_QUERY ), $q );
			$this->assertSame( 'bbai-library', $q['page'] );
			$this->assertSame( 'missing', $q['status'] );
			$this->assertSame( 'auditor', $q['source'] );
			$this->assertNull( consume_auditor_landing(), 'Landing happens only once.' );
			// Landing on the library with the args again must not re-fire the event.
			$this->visit( $q );
			$this->assertCount( 1, $this->events() );
		}

		public function test_no_landing_without_auditor_link(): void {
			mark_auditor_landing_pending();
			$this->assertNull( consume_auditor_landing() );
		}
	}
}
