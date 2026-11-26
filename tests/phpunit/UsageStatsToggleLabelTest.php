<?php
/**
 * 4.6.147 copy: the Settings usage analytics toggle label no longer says
 * "anonymous", and neither does the card description. Option, field and action names are unchanged.
 */
declare(strict_types=1);

namespace {
	use PHPUnit\Framework\TestCase;

	final class UsageStatsToggleLabelTest extends TestCase {
		public function test_toggle_label(): void {
			$s = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-usage-analytics.php' );
			$this->assertStringContainsString( "esc_html_e('Share usage stats with OpptiAI', 'beepbeep-ai-alt-text-generator')", $s );
			$this->assertStringNotContainsString( 'Share anonymous usage analytics', $s );
			$this->assertStringContainsString( 'name="bbai_usage_analytics"', $s );
		}

		public function test_card_description_and_label_never_say_anonymous(): void {
			$s = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-usage-analytics.php' );
			$this->assertStringContainsString( "esc_html_e('Help us improve OpptiAI Alt Text by sharing usage stats: which screens and buttons are used, and whether actions succeed. We never send ALT text, file names, image content, emails or passwords. Off unless you turn it on.', 'beepbeep-ai-alt-text-generator')", $s );
			// Every translatable string in the card (title, description, label, on/off lines, help text).
			preg_match_all( "/(?:esc_html_e|esc_html__|__)\\('((?:[^'\\\\]|\\\\.)*)'/", $s, $m );
			$this->assertGreaterThanOrEqual( 7, count( $m[1] ) );
			foreach ( $m[1] as $str ) {
				$this->assertStringNotContainsStringIgnoringCase( 'anonymous', $str );
			}
			$this->assertStringContainsString( 'id="bbai-usage-analytics-toggle"', $s );
		}
	}
}
