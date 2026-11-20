<?php
/**
 * First-run guidance: Get started link, one-time post-activation notice,
 * guest hero states with nothing to generate, and the guest usage analytics
 * card (opt-in, same stored choice as Settings, nothing sent before yes).
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_First_Run as FR;
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class FirstRunGuidanceTest extends TestCase {
		const NOTICE_TEXT = 'OpptiAI Alt Text is ready. Upload images, then click Generate on the OpptiAI Alt Text screen.';
		const NO_IMAGES   = 'Upload a few images, then click Generate to see alt text in seconds. No account needed for your first results.';
		const ALL_HAVE    = 'Every image here already has alt text. Upload an image, or pick one to regenerate, then click Generate.';

		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-first-run.php';
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-first-run.php';
			$_GET                              = array();
			$_POST                             = array();
			$GLOBALS['bbai_test_remote_posts'] = array();
			foreach ( array( FR::NOTICE_OPTION, BBAI_Telemetry::CONSENT_OPTION, BBAI_Telemetry::CONSENT_SOURCE_OPTION, BBAI_Telemetry::OPT_IN_LIFECYCLE_SENT_OPTION, BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION ) as $key ) {
				unset( $GLOBALS['bbai_test_options'][ $key ] );
			}
		}

		private function notice_html(): string {
			ob_start();
			FR::render_notice();
			return (string) ob_get_clean();
		}

		private function card_html(): string {
			ob_start();
			FR::render_consent_card();
			return (string) ob_get_clean();
		}

		private function text( string $html ): string {
			return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES ) ) );
		}

		// ---- 1. Get started link + one-time notice ----

		public function test_get_started_action_link_is_first_and_points_to_dashboard(): void {
			FR::init();
			$hook = 'plugin_action_links_beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
			$this->assertArrayHasKey( $hook, $GLOBALS['fr']['filters'] );
			$links = FR::action_links( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );
			$this->assertSame( array( 'bbai_get_started', 'deactivate' ), array_keys( $links ) );
			$this->assertSame( '<a href="https://example.com/wp-admin/admin.php?page=bbai">Get started</a>', $links['bbai_get_started'] );
		}

		public function test_get_started_link_hidden_from_users_who_cannot_manage(): void {
			$GLOBALS['fr']['caps'] = array( 'activate_plugins' => true );
			$this->assertSame( array( 'deactivate' => 'x' ), FR::action_links( array( 'deactivate' => 'x' ) ) );
		}

		public function test_no_notice_before_activation(): void {
			$this->assertSame( '', $this->notice_html() );
		}

		public function test_notice_set_on_activation_with_exact_copy_and_button(): void {
			FR::mark_activated();
			$this->assertNotFalse( get_option( FR::NOTICE_OPTION, false ) );
			$html = $this->notice_html();
			$this->assertStringContainsString( 'data-bbai-first-run-notice="1"', $html );
			$this->assertStringContainsString( self::NOTICE_TEXT, $this->text( $html ) );
			$this->assertMatchesRegularExpression( '#<a class="button button-primary" href="https://example.com/wp-admin/admin.php\?page=bbai">Open OpptiAI Alt Text</a>#', $html );
			// Dismiss is a nonce'd admin-post link.
			$this->assertStringContainsString( 'admin-post.php?action=bbai_first_run_notice_dismiss&amp;_wpnonce=nonce-bbai_first_run_notice_dismiss', $html );
		}

		public function test_notice_hidden_on_plugin_screen_and_cleared_by_first_visit(): void {
			FR::mark_activated();
			$_GET['page'] = 'bbai';
			$this->assertSame( '', $this->notice_html() );
			FR::maybe_clear_on_plugin_screen();
			$this->assertFalse( get_option( FR::NOTICE_OPTION, false ) );
			$_GET = array();
			$this->assertSame( '', $this->notice_html(), 'shown once: gone after the plugin screen was opened' );
		}

		public function test_dismissal_sticks(): void {
			FR::mark_activated();
			$this->assertNotSame( '', $this->notice_html() );
			try {
				FR::handle_dismiss();
				$this->fail( 'expected redirect' );
			} catch ( BBAI_Test_First_Run_Redirect $e ) {
				$this->assertSame( 'https://example.com/wp-admin/plugins.php', $e->getMessage() );
			}
			$this->assertFalse( get_option( FR::NOTICE_OPTION, false ) );
			$this->assertSame( '', $this->notice_html() );
			$this->assertSame( '', $this->notice_html() );
		}

		public function test_dismiss_requires_nonce(): void {
			FR::mark_activated();
			$GLOBALS['fr']['nonce_ok'] = false;
			try {
				FR::handle_dismiss();
				$this->fail( 'expected nonce failure' );
			} catch ( \RuntimeException $e ) {
				$this->assertStringContainsString( 'bad nonce', $e->getMessage() );
			}
			$this->assertNotFalse( get_option( FR::NOTICE_OPTION, false ) );
		}

		public function test_notice_not_shown_to_users_without_capability(): void {
			FR::mark_activated();
			$GLOBALS['fr']['caps'] = array( 'read' => true );
			$this->assertSame( '', $this->notice_html() );
			// A visit by a non-capable user does not clear it for the admin.
			$_GET['page'] = 'bbai';
			FR::maybe_clear_on_plugin_screen();
			$this->assertNotFalse( get_option( FR::NOTICE_OPTION, false ) );
			// And they cannot dismiss it.
			$this->expectException( \RuntimeException::class );
			FR::handle_dismiss();
		}

		public function test_plugin_cap_alone_is_enough(): void {
			FR::mark_activated();
			$GLOBALS['fr']['caps'] = array( 'manage_bbbbai_text' => true );
			$this->assertNotSame( '', $this->notice_html() );
		}

		public function test_activation_hook_marks_notice_and_no_redirect_added(): void {
			$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'beepbeep-ai-alt-text-generator.php' );
			$start = strpos( $src, 'function beepbeepai_activate_current_site()' );
			$body  = substr( $src, $start, strpos( $src, "\n}\n", $start ) - $start );
			$this->assertStringContainsString( 'BBAI_First_Run::mark_activated();', $body );
			$this->assertStringContainsString( "BBAI_First_Run::init();", $src );
			$fr = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-first-run.php' );
			$this->assertStringNotContainsString( 'wp_redirect(', $fr );
			$this->assertSame( 1, substr_count( $fr, 'wp_safe_redirect(' ), 'only the dismiss handler redirects' );
		}

		// ---- 2. Guest hero with nothing to generate ----

		/**
		 * @param array<string,int> $state
		 */
		private function hero( array $state ): string {
			$bbai_has_connected_account = false;
			$bbai_state_missing_count   = $state['missing'] ?? 0;
			$bbai_state_weak_count      = $state['weak'] ?? 0;
			$bbai_state_optimized_count = $state['optimized'] ?? 0;
			$bbai_state_total_images    = $state['total'] ?? 0;
			$bbai_product_state_model   = array( 'trial' => array( 'limit' => 10, 'used' => $state['used'] ?? 0, 'remaining' => 10 - ( $state['used'] ?? 0 ) ) );
			ob_start();
			require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-hero.php';
			return (string) ob_get_clean();
		}

		private function cta_cluster( string $html ): string {
			$start = strpos( $html, 'bbai-guest-hero__cta-cluster' );
			return substr( $html, $start, strpos( $html, 'bbai-guest-hero__login', $start ) - $start );
		}

		private function assert_upload_primary_register_secondary( string $html ): void {
			$cluster = $this->cta_cluster( $html );
			$this->assertMatchesRegularExpression( '#<a\s+href="https://example.com/wp-admin/media-new.php"\s+class="bbai-btn bbai-btn-primary bbai-li-btn-primary"\s+data-bbai-guest-upload-cta="1"#', $cluster );
			$this->assertStringContainsString( '>Upload images</a>', $cluster );
			$this->assertMatchesRegularExpression( '#class="bbai-btn bbai-btn-secondary bbai-li-btn-secondary"[^>]*data-action="show-auth-modal"#s', $cluster );
			$this->assertStringContainsString( 'Create Free Account', $cluster );
			$this->assertStringNotContainsString( 'data-action="generate-missing"', $cluster );
			$this->assertStringNotContainsString( 'Click Generate below', $this->text( $html ) );
			// Upload sits before Create Free Account.
			$this->assertLessThan( strpos( $cluster, 'Create Free Account' ), strpos( $cluster, 'Upload images' ) );
		}

		public function test_empty_media_library_shows_upload_copy_and_button(): void {
			$html = $this->hero( array( 'total' => 0 ) );
			$this->assertStringContainsString( self::NO_IMAGES, $this->text( $html ) );
			$this->assertStringNotContainsString( self::ALL_HAVE, $this->text( $html ) );
			$this->assert_upload_primary_register_secondary( $html );
		}

		public function test_all_images_have_alt_shows_regenerate_copy_and_upload_button(): void {
			$html = $this->hero( array( 'total' => 3, 'optimized' => 3 ) );
			$this->assertStringContainsString( self::ALL_HAVE, $this->text( $html ) );
			$this->assertStringNotContainsString( self::NO_IMAGES, $this->text( $html ) );
			$this->assert_upload_primary_register_secondary( $html );
		}

		public function test_weak_only_no_longer_signup_wall(): void {
			$html = $this->hero( array( 'total' => 2, 'weak' => 2 ) );
			$this->assertStringContainsString( self::ALL_HAVE, $this->text( $html ) );
			$this->assert_upload_primary_register_secondary( $html );
		}

		public function test_in_progress_trial_with_nothing_missing_gets_upload(): void {
			$html = $this->hero( array( 'total' => 3, 'optimized' => 3, 'used' => 3 ) );
			$this->assertStringContainsString( self::ALL_HAVE, $this->text( $html ) );
			$this->assert_upload_primary_register_secondary( $html );
		}

		public function test_missing_images_keep_generate_primary(): void {
			$html    = $this->hero( array( 'total' => 3, 'missing' => 3 ) );
			$cluster = $this->cta_cluster( $html );
			$this->assertStringContainsString( 'data-action="generate-missing"', $cluster );
			$this->assertStringNotContainsString( 'Upload images', $cluster );
			$this->assertStringContainsString( 'Click Generate below', $this->text( $html ) );
		}

		public function test_exhausted_trial_unchanged(): void {
			$html = $this->hero( array( 'total' => 3, 'optimized' => 3, 'used' => 10 ) );
			$this->assertStringNotContainsString( 'Upload images', $this->cta_cluster( $html ) );
			$this->assertStringNotContainsString( self::ALL_HAVE, $this->text( $html ) );
		}

		public function test_no_upload_button_without_upload_cap(): void {
			$GLOBALS['fr']['caps'] = array( 'manage_options' => true );
			$html = $this->hero( array( 'total' => 0 ) );
			$this->assertStringNotContainsString( 'Upload images', $this->cta_cluster( $html ) );
		}

		// ---- 4. Guest usage analytics card ----

		public function test_card_shows_for_unanswered_admin_and_sends_nothing(): void {
			$html = $this->card_html();
			$this->assertStringContainsString( 'data-bbai-guest-consent-card="1"', $html );
			$this->assertStringContainsString( 'name="action" value="bbai_save_usage_analytics"', $html );
			$this->assertStringContainsString( 'value="nonce-bbai_save_usage_analytics"', $html );
			$this->assertMatchesRegularExpression( '#name="bbai_usage_analytics" value="1"[^>]*>Yes, share<#', $html );
			$this->assertMatchesRegularExpression( '#name="bbai_usage_analytics" value="0"[^>]*>No thanks<#', $html );
			$text = $this->text( $html );
			$this->assertStringContainsString( 'anonymous usage events', $text );
			$this->assertStringContainsString( 'No personal data, no image content', $text );
			$this->assertStringContainsString( 'change this later', $text );
			// Default off: nothing stored, nothing sent, no consent.
			$this->assertFalse( get_option( BBAI_Telemetry::CONSENT_OPTION, false ) );
			$this->assertFalse( BBAI_Telemetry::has_telemetry_consent() );
			$this->assertSame( array(), $GLOBALS['bbai_test_remote_posts'] );
		}

		private function submit_choice( string $value ): void {
			$_POST['bbai_usage_analytics'] = $value;
			try {
				BBAI_Telemetry::handle_consent_form();
				$this->fail( 'expected redirect' );
			} catch ( BBAI_Test_First_Run_Redirect $e ) {
				$this->assertStringContainsString( 'bbai_usage_analytics_saved=' . ( '1' === $value ? 'on' : 'off' ), $e->getMessage() );
			}
		}

		public function test_yes_writes_the_settings_option_and_sends_opt_in_snapshot(): void {
			$this->submit_choice( '1' );
			// Same stored choice the Settings toggle writes.
			$this->assertSame( 'yes', get_option( BBAI_Telemetry::CONSENT_OPTION ) );
			$this->assertSame( 'settings', get_option( BBAI_Telemetry::CONSENT_SOURCE_OPTION ) );
			$this->assertTrue( BBAI_Telemetry::has_telemetry_consent() );
			$this->assertTrue( BBAI_Telemetry::stored_consent_is_yes() );
			// The existing late opt-in snapshot ran only after yes.
			$events = array_map(
				static function ( $call ) {
					$body = json_decode( (string) $call['args']['body'], true );
					return $body['event'] ?? '';
				},
				$GLOBALS['bbai_test_remote_posts']
			);
			$this->assertContains( 'plugin_activated', $events );
			// Card is gone; the one-line change form shows instead.
			$html = $this->card_html();
			$this->assertStringNotContainsString( 'data-bbai-guest-consent-card', $html );
			$this->assertStringContainsString( 'data-bbai-guest-consent-line="1"', $html );
			$this->assertStringContainsString( 'Turn off', $html );
		}

		public function test_no_thanks_saves_explicit_no_and_hides_card(): void {
			$this->submit_choice( '0' );
			$this->assertSame( 'no', get_option( BBAI_Telemetry::CONSENT_OPTION ) );
			$this->assertSame( 'settings', get_option( BBAI_Telemetry::CONSENT_SOURCE_OPTION ) );
			$this->assertFalse( BBAI_Telemetry::has_telemetry_consent() );
			$this->assertSame( array(), $GLOBALS['bbai_test_remote_posts'] );
			$html = $this->card_html();
			$this->assertStringNotContainsString( 'data-bbai-guest-consent-card', $html );
			$this->assertStringContainsString( 'Turn on', $html );
		}

		public function test_settings_answer_also_hides_card(): void {
			BBAI_Telemetry::set_consent( false );
			$this->assertFalse( FR::should_show_consent_card() );
		}

		public function test_legacy_implied_yes_is_not_an_answer(): void {
			update_option( BBAI_Telemetry::CONSENT_OPTION, 'yes' );
			$this->assertTrue( FR::should_show_consent_card() );
		}

		public function test_card_not_shown_to_non_admins(): void {
			$GLOBALS['fr']['caps'] = array( 'manage_bbbbai_text' => true );
			$this->assertSame( '', $this->card_html() );
		}

		public function test_card_not_shown_when_wp_config_forces_choice(): void {
			define( 'BBAI_TELEMETRY_CONSENT', false );
			$this->assertSame( '', $this->card_html() );
		}

		public function test_card_rendered_below_hero_on_guest_dashboard_only(): void {
			$src  = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-body.php' );
			$hero = strpos( $src, "require \$bbai_hero_partial;" );
			$card = strpos( $src, 'BBAI_First_Run::render_consent_card();' );
			$this->assertNotFalse( $card );
			$this->assertGreaterThan( $hero, $card );
			$this->assertSame( 1, substr_count( $src, 'BBAI_First_Run::render_consent_card();' ) );
		}
	}
}
