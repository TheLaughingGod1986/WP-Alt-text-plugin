<?php
/**
 * Runs the telemetry opt-in scripts in their own PHP process (they load the
 * real BBAI_Telemetry class, which other tests replace with a stub).
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TelemetryOptinTest extends TestCase {
	/**
	 * @return array<string,array{string,string}>
	 */
	public static function scripts(): array {
		return array(
			'opt-in only, anonymous payload' => array( 'optin-run.php', 'OK: usage stats are opt-in only; payload anonymous' ),
			'sign-up body without consent'   => array( 'signup-body-run.php', 'OK: sign-up body carries signup_source + Auditor attribution regardless of consent' ),
		);
	}

	/**
	 * @dataProvider scripts
	 */
	public function test_script( string $file, string $expect ): void {
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/telemetry/' . $file ) . ' 2>&1';
		exec( $cmd, $out, $code );
		$text = implode( "\n", $out );
		$this->assertSame( 0, $code, $text );
		$this->assertStringContainsString( $expect, $text );
		$this->assertStringNotContainsString( 'Warning', $text );
	}
}
