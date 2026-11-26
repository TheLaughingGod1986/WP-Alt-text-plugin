<?php
/**
 * 4.6.147: the 0-credit completion modal's events (out_of_credits_banner_shown,
 * upgrade_cta_clicked, upgrade_modal_opened with source=completion_modal) are
 * forwarded by the server bridge with source kept as sent, and nothing is
 * forwarded without opt-in. There is no source-value allowlist on the bridge;
 * the event allowlist is unchanged.
 *
 * wp_remote_post is recorded, never sent. Run offline: unshare -rn vendor/bin/phpunit
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class CompletionModalCapEventsBridgeTest extends TestCase {
		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			}
			unset( $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_OPTION ], $GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_SOURCE_OPTION ] );
		}

		private function core() {
			require __DIR__ . '/stubs-process-queue.php';
			if ( ! function_exists( 'date_i18n' ) ) {
				function date_i18n( $format, $ts = false ) { return gmdate( $format, $ts ? (int) $ts : time() ); }
			}
			if ( ! function_exists( 'wp_date' ) ) {
				function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?? time() ); }
			}
			if ( ! function_exists( 'add_query_arg' ) ) {
				function add_query_arg( array $args, string $url ) {
					return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
				}
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
			$ref = new \ReflectionClass( \BeepBeepAI\AltTextGenerator\Core::class );
			return $ref->newInstanceWithoutConstructor();
		}

		/** What the browser queues for the completion modal (see tests/js/completion-modal-cap-events.test.js). */
		private function events(): array {
			$base = array(
				'page'             => 'alt_library',
				'plan'             => 'free',
				'plan_type'        => 'free',
				'auth_state'       => 'authenticated',
				'is_signed_in'     => true,
				'quota_type'       => 'monthly_account',
				'credits_remaining' => 0,
				'source'           => 'completion_modal',
				'_bbai_client_batched' => true,
			);
			return array(
				'out_of_credits_banner_shown' => $base,
				'upgrade_cta_clicked'         => array_merge( $base, array( 'trigger' => 'credits_exhausted', 'context' => 'generation_complete', 'trigger_location' => 'completion_modal', 'current_plan' => 'free' ) ),
				'upgrade_modal_opened'        => array_merge( $base, array( 'trigger' => 'credits_exhausted', 'reason' => 'limit_reached' ) ),
			);
		}

		private function forward( $core, string $name, array $props ): void {
			$m = new \ReflectionMethod( $core, 'forward_client_telemetry_to_posthog' );
			$m->setAccessible( true );
			$m->invoke( $core, $name, $props );
		}

		public function test_opted_in_each_event_is_forwarded_once_with_source_completion_modal(): void {
			$core = $this->core();
			BBAI_Telemetry::set_consent( true );
			foreach ( $this->events() as $name => $props ) {
				$GLOBALS['bbai_test_remote_posts'] = array();
				$this->forward( $core, $name, $props );
				$this->assertCount( 1, $GLOBALS['bbai_test_remote_posts'], $name );
				$event = json_decode( (string) $GLOBALS['bbai_test_remote_posts'][0]['args']['body'], true );
				$this->assertSame( $name, $event['event'] );
				$p = $event['properties'];
				$this->assertSame( 'completion_modal', $p['source'], $name );
				if ( 'upgrade_modal_opened' !== $name ) {
					// upgrade_modal_opened's plan is resolved server-side (account plan), as before.
					$this->assertSame( 'free', $p['plan'], $name );
				}
				$this->assertSame( 0, $p['credits_remaining'], $name );
				$this->assertArrayNotHasKey( '_bbai_client_batched', $p );
			}
		}

		public function test_nothing_is_forwarded_without_opt_in(): void {
			$core = $this->core();
			BBAI_Telemetry::set_consent( false );
			$GLOBALS['bbai_test_remote_posts'] = array();
			foreach ( $this->events() as $name => $props ) {
				$this->forward( $core, $name, $props );
			}
			$this->assertCount( 0, $GLOBALS['bbai_test_remote_posts'] );
		}

		public function test_never_consented_sends_nothing(): void {
			$core = $this->core();
			$GLOBALS['bbai_test_remote_posts'] = array();
			foreach ( $this->events() as $name => $props ) {
				$this->forward( $core, $name, $props );
			}
			$this->assertCount( 0, $GLOBALS['bbai_test_remote_posts'] );
		}
	}
}
