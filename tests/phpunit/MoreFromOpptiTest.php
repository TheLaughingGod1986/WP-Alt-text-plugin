<?php
/**
 * "More from Oppti" panel: shown only on the Dashboard screen after a
 * successful generation, hidden after dismiss, never lists installed plugins,
 * connected vs guest wording, links, copy rules, consent-gated events.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\BBAI_More_From_Oppti as MFO;
	use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
	use PHPUnit\Framework\TestCase;

	#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
	final class MoreFromOpptiTest extends TestCase {
		protected function setUp(): void {
			parent::setUp();
			require_once __DIR__ . '/stubs-more-from-oppti.php';
			require_once __DIR__ . '/stubs-telemetry-capture.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-more-from-oppti.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
			$_GET = array( 'page' => 'bbai' );
			$GLOBALS['bbai_test_remote_posts'] = array();
			$GLOBALS['mfo']['plugins']         = array(
				'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' => array( 'TextDomain' => 'beepbeep-ai-alt-text-generator' ),
			);
			foreach ( array( 'bbai_first_success_at', 'active_plugins', BBAI_Telemetry::CONSENT_OPTION, BBAI_Telemetry::CONSENT_SOURCE_OPTION, BBAI_Telemetry::ACTIVATION_MARKER_OPTION, BBAI_Telemetry::ACTIVATION_SIBLINGS_OPTION, BBAI_Telemetry::LIFECYCLE_QUEUE_OPTION, BBAI_Telemetry::INSTALLED_MARKER_OPTION, BBAI_Telemetry::LEGACY_INSTALLED_MARKER_OPTION ) as $key ) {
				unset( $GLOBALS['bbai_test_options'][ $key ] );
			}
		}

		private function html( bool $connected = false, bool $dashboard_signal = false ): string {
			ob_start();
			MFO::render( $connected, $dashboard_signal );
			return (string) ob_get_clean();
		}

		private function visible_text( string $html ): string {
			return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES ) ) );
		}

		public function test_hidden_before_any_success(): void {
			$html = $this->html();
			// Markup waits hidden; only a successful generation in this page view reveals it.
			$this->assertStringContainsString( 'data-bbai-mfo="1"', $html );
			$this->assertMatchesRegularExpression( '/<section[^>]*\shidden data-await-success="1"/', $html );
			$this->assertFalse( MFO::should_render( array( 'on_screen' => true, 'can_manage' => true, 'has_success' => false, 'dismissed' => false, 'candidates' => array( 'titles' ) ) ) );
		}

		public function test_hidden_after_first_run_error(): void {
			// A failed first run records no success marker and no generated count.
			$html = $this->html( false, false );
			$this->assertMatchesRegularExpression( '/<section[^>]*\shidden data-await-success="1"/', $html );
		}

		public function test_visible_after_success(): void {
			update_option( 'bbai_first_success_at', time() );
			$html = $this->html();
			$this->assertStringContainsString( 'data-bbai-mfo="1"', $html );
			$this->assertDoesNotMatchRegularExpression( '/<section[^>]*\shidden/', $html );
			// Dashboard signal alone (older sites without the marker) also counts.
			unset( $GLOBALS['bbai_test_options']['bbai_first_success_at'] );
			$this->assertDoesNotMatchRegularExpression( '/<section[^>]*\shidden/', $this->html( false, true ) );
		}

		public function test_hidden_after_dismiss_and_dismiss_control_present(): void {
			update_option( 'bbai_first_success_at', time() );
			$html = $this->html();
			$this->assertStringContainsString( 'name="action" value="bbai_more_from_oppti_dismiss"', $html );
			$this->assertStringContainsString( 'aria-label="Dismiss this More from Oppti panel"', $html );
			update_user_meta( 7, MFO::DISMISS_META, time() );
			$this->assertSame( '', $this->html() );
			// Nothing is enqueued for a user who dismissed.
			$GLOBALS['mfo']['enqueued'] = array();
			MFO::enqueue();
			$this->assertSame( array(), $GLOBALS['mfo']['enqueued'] );
		}

		public function test_hides_installed_siblings(): void {
			update_option( 'bbai_first_success_at', time() );
			$GLOBALS['mfo']['plugins']['opptiai-titles/beepbeep-titles.php'] = array( 'TextDomain' => 'beepbeep-titles' );
			// Renamed folder still matched by text domain.
			$GLOBALS['mfo']['plugins']['website-auditor-1.0.2/opptiai-website-auditor.php'] = array( 'TextDomain' => 'opptiai-website-auditor' );
			$html = $this->html( true );
			$this->assertStringNotContainsString( 'data-to="titles"', $html );
			$this->assertStringNotContainsString( 'data-to="auditor"', $html );
			$this->assertStringNotContainsString( 'data-to="alt"', $html );
			$this->assertStringContainsString( 'data-to="linking"', $html );
			// Everything installed: no panel at all.
			$GLOBALS['mfo']['plugins']['opptiai-internal-linking/opptiai-internal-linking.php'] = array( 'TextDomain' => 'opptiai-internal-linking' );
			$this->assertSame( '', $this->html( true ) );
			// Never suggests itself; active-only detection also hides a sibling.
			$this->assertSame( array( 'titles', 'auditor', 'linking' ), MFO::candidate_ids( array(), array() ) );
			$this->assertSame( array( 'auditor', 'linking' ), MFO::candidate_ids( array(), array( 'titles' ) ) );
		}

		public function test_connected_wording_names_only_login_plugins(): void {
			update_option( 'bbai_first_success_at', time() );
			$text = $this->visible_text( $this->html( true ) );
			$this->assertStringContainsString( 'Your same Oppti login also works with Titles.', $text );
			$this->assertStringNotContainsString( 'login also works with Titles, ', $text );
			$this->assertStringNotContainsString( 'works with Website Auditor', $text );
			$this->assertStringNotContainsString( 'works with Internal Linking', $text );
		}

		public function test_intro_cases(): void {
			// Connected: login line names only Alt Text / Titles, never IL or the Auditor.
			$this->assertSame( 'Your same Oppti login also works with Titles.', MFO::intro_line( true, array( 'titles', 'auditor', 'linking' ) ) );
			$this->assertSame( 'Your same Oppti login also works with Titles.', MFO::intro_line( true, array( 'titles' ) ) );
			// Connected, only IL and/or Auditor missing: no intro at all.
			$this->assertSame( '', MFO::intro_line( true, array( 'auditor', 'linking' ) ) );
			$this->assertSame( '', MFO::intro_line( true, array( 'auditor' ) ) );
			$this->assertSame( '', MFO::intro_line( true, array( 'linking' ) ) );
			// Guests never get the login line, whatever is missing.
			foreach ( array( array( 'titles', 'auditor', 'linking' ), array( 'titles' ), array( 'auditor' ), array( 'linking' ) ) as $ids ) {
				$this->assertSame( 'More from OpptiAI, for the rest of the job.', MFO::intro_line( false, $ids ) );
			}
			// Two login plugins (as seen from another plugin's catalog) join with "and".
			$this->assertSame( 'Alt Text and Titles', MFO::join_names( array( 'Alt Text', 'Titles' ) ) );
		}

		public function test_connected_rows_only_when_titles_installed(): void {
			update_option( 'bbai_first_success_at', time() );
			$GLOBALS['mfo']['plugins']['opptiai-titles/beepbeep-titles.php'] = array( 'TextDomain' => 'beepbeep-titles' );
			$html = $this->html( true );
			$this->assertStringNotContainsString( 'bbai-more-from-oppti__intro', $html );
			$this->assertStringNotContainsString( 'login', strtolower( $this->visible_text( $html ) ) );
			$this->assertStringContainsString( 'data-to="auditor"', $html );
			$this->assertStringContainsString( 'data-to="linking"', $html );
		}

		public function test_guest_wording_has_no_login_line(): void {
			update_option( 'bbai_first_success_at', time() );
			$html = $this->html( false );
			$text = $this->visible_text( $html );
			$this->assertStringContainsString( 'More from OpptiAI, for the rest of the job.', $text );
			$this->assertStringNotContainsString( 'login', strtolower( $text ) );
			$this->assertStringContainsString( 'data-account-state="guest"', $html );
		}

		public function test_rows_badges_and_copy_rules(): void {
			update_option( 'bbai_first_success_at', time() );
			$text = $this->visible_text( $this->html( true ) );
			$this->assertStringContainsString( 'Find weak SEO titles and meta descriptions. Install Titles Free to try.', $text );
			$this->assertStringContainsString( 'See what to fix on your site first. Install Website Auditor A free plugin. No account needed.', $text );
			$this->assertStringContainsString( 'Find pages with no internal links. Install Internal Linking', $text );
			$this->assertStringNotContainsString( 'Install Internal Linking Free', $text );
			foreach ( MFO::copy() as $key => $line ) {
				$this->assertStringNotContainsString( "\u{2014}", $line, $key );
				$this->assertDoesNotMatchRegularExpression( '/\d\s*free|free\s*\d/i', $line, $key );
				$this->assertDoesNotMatchRegularExpression( '/complian|ADA|WCAG|guarantee/i', $line, $key );
			}
		}

		public function test_links_installer_or_tagged_wporg(): void {
			$this->assertSame(
				array( 'url' => 'https://example.com/wp-admin/plugin-install.php?s=opptiai-titles&tab=search&type=term', 'target' => 'installer' ),
				MFO::link_for( 'titles', true )
			);
			$wporg = MFO::link_for( 'auditor', false );
			$this->assertSame( 'wporg', $wporg['target'] );
			$this->assertSame( 'https://wordpress.org/plugins/opptiai-website-auditor/?utm_source=plugin&utm_medium=crosssell&utm_campaign=push-nov26&utm_content=xs-alt-to-auditor', $wporg['url'] );

			update_option( 'bbai_first_success_at', time() );
			$GLOBALS['mfo']['caps']['install_plugins'] = false;
			$html = $this->html();
			$this->assertStringContainsString( 'data-link-target="wporg"', $html );
			$this->assertStringContainsString( 'target="_blank"', $html );
			$this->assertStringNotContainsString( 'plugin-install.php', $html );
		}

		public function test_no_output_on_other_screens_or_without_capability(): void {
			update_option( 'bbai_first_success_at', time() );
			foreach ( array( 'bbai-library', 'bbai-settings', 'bbai-checkout', '', 'opptiai-titles' ) as $page ) {
				$_GET = '' === $page ? array() : array( 'page' => $page );
				$this->assertSame( '', $this->html(), 'page=' . $page );
				$GLOBALS['mfo']['enqueued'] = array();
				MFO::enqueue();
				$this->assertSame( array(), $GLOBALS['mfo']['enqueued'], 'enqueue page=' . $page );
			}
			$_GET                       = array( 'page' => 'bbai' );
			$GLOBALS['mfo']['is_admin'] = false;
			$this->assertSame( '', $this->html() );
			$GLOBALS['mfo']['is_admin'] = true;
			$GLOBALS['mfo']['caps']     = array();
			$this->assertSame( '', $this->html() );
		}

		public function test_client_events_disabled_without_consent(): void {
			MFO::enqueue();
			$this->assertFalse( $GLOBALS['mfo']['localized']['BBAI_MORE_FROM_OPPTI']['telemetry']['enabled'] );
			// Even a forged client event is never sent to PostHog without consent.
			BBAI_Telemetry::capture_posthog_event( 'crosssell_clicked', 'test_site_install_id_0123456789ab', array( 'from_plugin' => 'alt', 'to_plugin' => 'titles' ) );
			$this->assertSame( array(), $GLOBALS['bbai_test_remote_posts'] );

			update_option( BBAI_Telemetry::CONSENT_OPTION, 'yes' );
			update_option( BBAI_Telemetry::CONSENT_SOURCE_OPTION, 'settings' );
			MFO::enqueue();
			$this->assertTrue( $GLOBALS['mfo']['localized']['BBAI_MORE_FROM_OPPTI']['telemetry']['enabled'] );
			BBAI_Telemetry::capture_posthog_event( 'crosssell_clicked', 'test_site_install_id_0123456789ab', array( 'from_plugin' => 'alt', 'to_plugin' => 'titles', 'link_target' => 'installer' ) );
			$this->assertCount( 1, $GLOBALS['bbai_test_remote_posts'] );
			$body = json_decode( (string) $GLOBALS['bbai_test_remote_posts'][0]['args']['body'], true );
			$this->assertSame( 'crosssell_clicked', $body['event'] );
			$this->assertSame( 'alt', $body['properties']['from_plugin'] );
			$this->assertSame( 'titles', $body['properties']['to_plugin'] );
		}

		public function test_forwarding_allowlist_includes_panel_events(): void {
			$src = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php' );
			foreach ( MFO::EVENTS as $event ) {
				$this->assertStringContainsString( "'" . $event . "'", $src );
			}
		}

		public function test_activation_siblings_only_with_consent(): void {
			update_option( 'active_plugins', array( 'opptiai-titles/beepbeep-titles.php', 'opptiai-website-auditor/opptiai-website-auditor.php', 'akismet/akismet.php', 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' ) );
			BBAI_Telemetry::record_activation_lifecycle();
			$this->assertSame( array(), $GLOBALS['bbai_test_remote_posts'] );
			$this->assertSame( 'auditor,titles', get_option( BBAI_Telemetry::ACTIVATION_SIBLINGS_OPTION ) );

			unset( $GLOBALS['bbai_test_options'][ BBAI_Telemetry::ACTIVATION_MARKER_OPTION ] );
			update_option( BBAI_Telemetry::CONSENT_OPTION, 'yes' );
			update_option( BBAI_Telemetry::CONSENT_SOURCE_OPTION, 'settings' );
			BBAI_Telemetry::record_activation_lifecycle();
			$events = array();
			foreach ( $GLOBALS['bbai_test_remote_posts'] as $call ) {
				$body                      = json_decode( (string) $call['args']['body'], true );
				$events[ $body['event'] ] = $body['properties'];
			}
			$this->assertArrayHasKey( 'plugin_activated', $events );
			$this->assertSame( 'auditor,titles', $events['plugin_activated']['siblings'] );
		}
	}
}
