<?php
/**
 * 4.6.147 copy: the Settings usage analytics toggle label no longer says
 * "anonymous". Option, field and action names are unchanged.
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
			$this->assertStringContainsString( 'id="bbai-usage-analytics-toggle"', $s );
		}
	}
}
