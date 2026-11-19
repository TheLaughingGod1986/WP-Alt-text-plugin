<?php
/**
 * No shipped file carries a staff or test email address or domain pattern
 * (test sites are matched by site_hash in analytics, never by email).
 * Scans every file the zip build would ship (same top-level exclusions).
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NoStaffPatternsShippedTest extends TestCase {
	/** Top-level paths scripts/build-plugin-zip.bash and .distignore never ship. */
	const NOT_SHIPPED = array( '.git', '.github', '.claude', '.cursor', '.vscode', 'node_modules', 'vendor', 'tests', 'scripts', 'docs', 'build', 'dist', 'output', 'test-results', 'playwright-report', '.wp-env', 'worktrees' );

	/** Strings that must not appear in shipped code. */
	const FORBIDDEN = array( 'qa.oppti.dev', 'TEST_EMAIL_SUFFIX', 'boxedtech', '^ben@', 'optti)' );

	private function shipped_files(): array {
		$root  = rtrim( BEEPBEEP_AI_PLUGIN_DIR, '/' );
		$files = array();
		$it    = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				static function ( $file, $key, $iterator ) use ( $root ) {
					$rel = ltrim( substr( $file->getPathname(), strlen( $root ) ), '/' );
					$top = explode( '/', $rel )[0];
					return ! in_array( $top, self::NOT_SHIPPED, true );
				}
			)
		);
		foreach ( $it as $file ) {
			if ( $file->isFile() && preg_match( '/\.(php|js|txt|json|pot|css|html|md)$/', $file->getFilename() ) ) {
				$files[] = $file->getPathname();
			}
		}
		return $files;
	}

	public function test_no_staff_or_test_email_patterns_in_shipped_files(): void {
		$files = $this->shipped_files();
		$this->assertGreaterThan( 100, count( $files ) );
		$hits = array();
		foreach ( $files as $path ) {
			$src = (string) file_get_contents( $path );
			foreach ( self::FORBIDDEN as $needle ) {
				if ( false !== stripos( $src, $needle ) ) {
					$hits[] = substr( $path, strlen( BEEPBEEP_AI_PLUGIN_DIR ) ) . ': ' . $needle;
				}
			}
		}
		$this->assertSame( array(), $hits );
	}

	public function test_dashboard_source_and_min_have_no_test_email_domain(): void {
		foreach ( array( 'assets/js/bbai-dashboard.js', 'assets/js/bbai-dashboard.min.js', 'assets/js/bbai-telemetry.js', 'assets/js/bbai-posthog.js' ) as $rel ) {
			$this->assertStringNotContainsString( 'qa.oppti.dev', (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . $rel ), $rel );
		}
	}

	public function test_is_internal_environment_ignores_email(): void {
		$src   = (string) file_get_contents( BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php' );
		$start = strpos( $src, 'function is_internal_environment' );
		$body  = substr( $src, $start, strpos( $src, "apply_filters(", $start ) - $start );
		$this->assertStringNotContainsString( '$email,', $body );
		$this->assertDoesNotMatchRegularExpression( '/preg_match\([^;]*\$email/s', $body );
	}
}
