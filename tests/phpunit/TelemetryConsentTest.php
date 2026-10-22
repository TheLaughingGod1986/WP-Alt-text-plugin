<?php
/**
 * Usage analytics are opt-in only: unset means no, activation never opts in,
 * the Settings choice is stored, and activation-implied 'yes' from older
 * builds is cleared once.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class TelemetryConsentTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'delete_option' ) ) {
			function delete_option( $key ) {
				unset( $GLOBALS['bbai_test_options'][ $key ] );
				return true;
			}
		}
		if ( ! function_exists( 'sanitize_key' ) ) {
			function sanitize_key( $value ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
			}
		}
		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return 0;
			}
		}
		if ( ! class_exists( BBAI_Telemetry::class, false ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-telemetry.php';
		}
		unset(
			$GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_OPTION ],
			$GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_SOURCE_OPTION ],
			$GLOBALS['bbai_test_options'][ BBAI_Telemetry::CONSENT_RESET_MARKER_OPTION ]
		);
	}

	public function test_unset_consent_means_no(): void {
		$this->assertFalse( BBAI_Telemetry::has_telemetry_consent() );
		$this->assertFalse( BBAI_Telemetry::stored_consent_is_yes() );
	}

	public function test_activation_does_not_opt_in(): void {
		// Mark as already installed/current so only the consent behaviour is exercised.
		update_option( BBAI_Telemetry::INSTALLED_MARKER_OPTION, '2026-10-22T00:00:00+00:00' );
		update_option( BBAI_Telemetry::CURRENT_VERSION_OPTION, BEEPBEEP_AI_VERSION );
		update_option( BBAI_Telemetry::ACTIVATION_MARKER_OPTION, '1' );
		try {
			BBAI_Telemetry::record_activation_lifecycle();
		} catch ( \Error $e ) {
			// A missing WordPress stub further down the lifecycle is irrelevant here.
		}
		$this->assertFalse( get_option( BBAI_Telemetry::CONSENT_OPTION, false ) );
		$this->assertFalse( BBAI_Telemetry::has_telemetry_consent() );
	}

	public function test_settings_choice_is_stored(): void {
		BBAI_Telemetry::set_consent( true );
		$this->assertSame( 'yes', get_option( BBAI_Telemetry::CONSENT_OPTION ) );
		$this->assertSame( 'settings', get_option( BBAI_Telemetry::CONSENT_SOURCE_OPTION ) );
		$this->assertTrue( BBAI_Telemetry::has_telemetry_consent() );

		BBAI_Telemetry::set_consent( false );
		$this->assertSame( 'no', get_option( BBAI_Telemetry::CONSENT_OPTION ) );
		$this->assertFalse( BBAI_Telemetry::has_telemetry_consent() );
	}

	public function test_activation_implied_yes_is_reset_once(): void {
		update_option( BBAI_Telemetry::CONSENT_OPTION, 'yes' );
		BBAI_Telemetry::maybe_reset_implied_consent();
		$this->assertFalse( get_option( BBAI_Telemetry::CONSENT_OPTION, false ) );
		$this->assertFalse( BBAI_Telemetry::has_telemetry_consent() );

		// After the one-time reset, a later Settings opt-in sticks.
		BBAI_Telemetry::set_consent( true );
		BBAI_Telemetry::maybe_reset_implied_consent();
		$this->assertTrue( BBAI_Telemetry::has_telemetry_consent() );
	}

	public function test_settings_opt_in_survives_reset(): void {
		BBAI_Telemetry::set_consent( true );
		BBAI_Telemetry::maybe_reset_implied_consent();
		$this->assertSame( 'yes', get_option( BBAI_Telemetry::CONSENT_OPTION ) );
		$this->assertTrue( BBAI_Telemetry::has_telemetry_consent() );
	}

	public function test_auto_yes_is_not_consent_before_reset_runs(): void {
		// e.g. a cron or front-end request on a 4.6.140 site before admin_init.
		update_option( BBAI_Telemetry::CONSENT_OPTION, 'yes' );
		$this->assertFalse( BBAI_Telemetry::has_telemetry_consent() );
	}
}
