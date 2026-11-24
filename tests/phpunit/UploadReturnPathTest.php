<?php
/**
 * After-upload return path: one-time per-user notice after an image upload
 * with no alt text, and the "Back to Generate ALT text" link on media-new.php.
 * Admin-only, dismissible, never on the plugin's own screens, sends nothing.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_First_Run as FR;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class UploadReturnPathTest extends TestCase {
		const NOTICE = 'Generate ALT text for these images.';
		const BUTTON = 'Open OpptiAI Alt Text';
		const LINK   = 'Back to Generate ALT text';

		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-first-run.php';
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-first-run.php';
			$_GET                              = array();
			$GLOBALS['bbai_test_remote_posts'] = array();
			$GLOBALS['fr']['user_id']          = 7;
			$GLOBALS['fr']['images']           = array( 101, 102 );
			$GLOBALS['fr']['alts']             = array( 102 => 'A red bicycle' );
			unset( $GLOBALS['pagenow'] );
		}

		private function capture( callable $fn ): string {
			ob_start();
			$fn();
			return (string) ob_get_clean();
		}

		private function notice(): string {
			return $this->capture( array( FR::class, 'render_upload_notice' ) );
		}

		private function text( string $html ): string {
			return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES ) ) );
		}

		public function test_hooks_registered(): void {
			FR::init();
			foreach ( array( 'add_attachment', 'admin_notices', 'admin_post_' . FR::UPLOAD_DISMISS_ACTION, 'post-upload-ui' ) as $hook ) {
				$this->assertArrayHasKey( $hook, $GLOBALS['fr']['filters'], $hook );
			}
		}

		public function test_no_notice_before_any_upload(): void {
			$this->assertSame( '', $this->notice() );
		}

		public function test_image_upload_without_alt_shows_notice_once_with_button(): void {
			FR::maybe_mark_upload( 101 );
			$html = $this->notice();
			$this->assertStringContainsString( 'data-bbai-upload-return-notice="1"', $html );
			$this->assertStringContainsString( self::NOTICE, $this->text( $html ) );
			$this->assertMatchesRegularExpression( '#<a class="button button-primary" href="https://example.com/wp-admin/admin.php\?page=bbai">' . self::BUTTON . '</a>#', $html );
			$this->assertStringContainsString( 'action=' . FR::UPLOAD_DISMISS_ACTION, $html );
			$this->assertStringContainsString( '_wpnonce=nonce-' . FR::UPLOAD_DISMISS_ACTION, $html );
			// A second upload does not stack or reset anything.
			$first = $GLOBALS['fr']['user_meta'][7][ FR::UPLOAD_NOTICE_META ];
			FR::maybe_mark_upload( 101 );
			$this->assertSame( $first, $GLOBALS['fr']['user_meta'][7][ FR::UPLOAD_NOTICE_META ] );
			$this->assertSame( 1, substr_count( $this->notice(), self::NOTICE ) );
		}

		public function test_notice_is_per_user(): void {
			FR::maybe_mark_upload( 101 );
			$GLOBALS['fr']['user_id'] = 8;
			$this->assertSame( '', $this->notice() );
		}

		public function test_upload_with_alt_or_non_image_sets_nothing(): void {
			FR::maybe_mark_upload( 102 );
			FR::maybe_mark_upload( 555 );
			$this->assertSame( '', $this->notice() );
			$this->assertArrayNotHasKey( 7, $GLOBALS['fr']['user_meta'] );
		}

		public function test_users_without_both_caps_get_nothing(): void {
			foreach ( array( array( 'manage_options' => true ), array( 'upload_files' => true ) ) as $caps ) {
				$GLOBALS['fr']['caps']      = $caps;
				$GLOBALS['fr']['user_meta'] = array();
				FR::maybe_mark_upload( 101 );
				$this->assertArrayNotHasKey( 7, $GLOBALS['fr']['user_meta'] );
				$GLOBALS['fr']['user_meta'][7][ FR::UPLOAD_NOTICE_META ] = 1;
				$this->assertSame( '', $this->notice() );
				$GLOBALS['pagenow'] = 'media-new.php';
				$this->assertSame( '', $this->capture( array( FR::class, 'render_upload_return_link' ) ) );
			}
		}

		public function test_plugin_capability_counts_as_admin(): void {
			$GLOBALS['fr']['caps'] = array( 'upload_files' => true, 'manage_bbbbai_text' => true );
			FR::maybe_mark_upload( 101 );
			$this->assertStringContainsString( self::NOTICE, $this->notice() );
		}

		public function test_never_on_plugin_screens_and_visit_clears_it(): void {
			FR::maybe_mark_upload( 101 );
			foreach ( array( 'bbai', 'bbai-library', 'bbai-settings' ) as $page ) {
				$_GET['page'] = $page;
				$this->assertSame( '', $this->notice(), $page );
			}
			FR::maybe_clear_on_plugin_screen();
			$_GET = array();
			$this->assertSame( '', $this->notice() );
		}

		public function test_other_admin_pages_show_it_until_dismissed(): void {
			FR::maybe_mark_upload( 101 );
			$_GET['page'] = 'some-other-plugin';
			$this->assertStringContainsString( self::NOTICE, $this->notice() );
			try {
				FR::handle_upload_dismiss();
				$this->fail( 'Expected redirect' );
			} catch ( BBAI_Test_First_Run_Redirect $e ) {
				$this->assertSame( 'https://example.com/wp-admin/plugins.php', $GLOBALS['fr']['redirected'] );
			}
			$this->assertSame( '', $this->notice() );
		}

		public function test_dismiss_rejects_bad_nonce_and_non_admins(): void {
			FR::maybe_mark_upload( 101 );
			$GLOBALS['fr']['nonce_ok'] = false;
			try {
				FR::handle_upload_dismiss();
				$this->fail( 'Expected nonce failure' );
			} catch ( \RuntimeException $e ) {
				$this->assertStringContainsString( 'bad nonce', $e->getMessage() );
			}
			$this->assertStringContainsString( self::NOTICE, $this->notice() );
			$GLOBALS['fr']['caps'] = array( 'upload_files' => true );
			$this->expectExceptionMessage( 'wp_die' );
			FR::handle_upload_dismiss();
		}

		public function test_not_outside_admin(): void {
			FR::maybe_mark_upload( 101 );
			$GLOBALS['fr']['is_admin'] = false;
			$this->assertSame( '', $this->notice() );
		}

		public function test_return_link_only_on_media_new_and_hidden_until_upload(): void {
			$this->assertSame( '', $this->capture( array( FR::class, 'render_upload_return_link' ) ) );
			$GLOBALS['pagenow'] = 'upload.php';
			$this->assertSame( '', $this->capture( array( FR::class, 'render_upload_return_link' ) ) );

			$GLOBALS['pagenow'] = 'media-new.php';
			$html = $this->capture( array( FR::class, 'render_upload_return_link' ) );
			$this->assertMatchesRegularExpression( '#<p class="bbai-upload-return-link" data-bbai-upload-return-link="1" hidden>#', $html );
			$this->assertMatchesRegularExpression( '#<a class="button" href="https://example.com/wp-admin/admin.php\?page=bbai">' . self::LINK . '</a>#', $html );
			$this->assertStringContainsString( "querySelector( '.edit-attachment' )", $html );
			$this->assertStringContainsString( "addEventListener( 'DOMContentLoaded'", $html );

			FR::maybe_mark_upload( 101 );
			$html = $this->capture( array( FR::class, 'render_upload_return_link' ) );
			$this->assertStringContainsString( 'data-bbai-upload-return-link="1">', $html );
			$this->assertStringNotContainsString( ' hidden>', $html );
		}

		public function test_nothing_is_sent(): void {
			FR::maybe_mark_upload( 101 );
			$this->notice();
			$GLOBALS['pagenow'] = 'media-new.php';
			$this->capture( array( FR::class, 'render_upload_return_link' ) );
			$this->assertSame( array(), $GLOBALS['bbai_test_remote_posts'] );
			$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-first-run.php' );
			$this->assertStringNotContainsString( 'wp_remote_', $src );
			$this->assertStringNotContainsString( 'BBAI_Telemetry::capture', $src );
		}

		public function test_uninstall_removes_user_meta(): void {
			$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'uninstall.php' );
			$this->assertStringContainsString( "delete_metadata( 'user', 0, 'bbai_upload_return_pending', '', true );", $src );
		}
	}
}
