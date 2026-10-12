<?php
/**
 * Schema upgrade to 1.1.0: idempotency and table shape.
 *
 * Uses a minimal $wpdb double. This covers the upgrade path an existing install
 * takes (1.0.0 -> 1.1.0). The fresh-install path additionally runs
 * upgrade_to_1_0_0(), which touches five other table classes and is exercised by
 * the manual QA checklist rather than here.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Repository;
use BeepBeepAI\AltTextGenerator\DB_Schema;
use PHPUnit\Framework\TestCase;

final class AuditMigrationTest extends TestCase {

	public static function setUpBeforeClass(): void {
		// dbDelta() lives behind ABSPATH in a real install; stand up the same path here.
		$upgrade_dir = ABSPATH . 'wp-admin/includes';
		if ( ! is_dir( $upgrade_dir ) ) {
			mkdir( $upgrade_dir, 0777, true );
		}

		$upgrade_file = $upgrade_dir . '/upgrade.php';
		if ( ! file_exists( $upgrade_file ) ) {
			file_put_contents(
				$upgrade_file,
				"<?php\nfunction dbDelta( \$sql ) {\n\t\$GLOBALS['bbai_test_dbdelta'][] = \$sql;\n\treturn array();\n}\n"
			);
		}

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/audit/class-image-audit-repository.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-db.php';
	}

	protected function setUp(): void {
		$GLOBALS['bbai_test_dbdelta'] = array();
		$GLOBALS['wpdb']              = new BBAI_Test_WPDB();
		unset( $GLOBALS['bbai_test_options'][ DB_Schema::OPTION_KEY ] );
	}

	/**
	 * SQL passed to dbDelta during the current test.
	 */
	private function dbdelta_calls(): array {
		return $GLOBALS['bbai_test_dbdelta'];
	}

	public function test_the_plugin_db_version_constant_matches_the_test_bootstrap(): void {
		// Guards against the bootstrap drifting away from the shipped constant.
		$plugin_file = file_get_contents( BEEPBEEP_AI_PLUGIN_FILE );

		$this->assertIsString( $plugin_file );
		$this->assertMatchesRegularExpression(
			"/define\(\s*'BEEPBEEP_AI_DB_VERSION',\s*'" . preg_quote( BEEPBEEP_AI_DB_VERSION, '/' ) . "'\s*\)/",
			$plugin_file
		);
	}

	public function test_upgrading_from_1_0_0_creates_the_audit_table(): void {
		update_option( DB_Schema::OPTION_KEY, '1.0.0' );

		DB_Schema::install();

		$this->assertCount( 1, $this->dbdelta_calls() );
		$this->assertStringContainsString( 'wptest_bbai_image_audit', $this->dbdelta_calls()[0] );
		$this->assertSame( BEEPBEEP_AI_DB_VERSION, get_option( DB_Schema::OPTION_KEY ) );
	}

	public function test_install_is_idempotent(): void {
		update_option( DB_Schema::OPTION_KEY, '1.0.0' );

		DB_Schema::install();
		$after_first = count( $this->dbdelta_calls() );

		DB_Schema::install();
		DB_Schema::install();

		$this->assertSame( $after_first, count( $this->dbdelta_calls() ), 'Repeat installs must be no-ops' );
	}

	public function test_maybe_upgrade_does_nothing_when_already_current(): void {
		update_option( DB_Schema::OPTION_KEY, BEEPBEEP_AI_DB_VERSION );

		DB_Schema::maybe_upgrade();

		$this->assertSame( array(), $this->dbdelta_calls() );
	}

	public function test_a_newer_stored_version_is_not_downgraded(): void {
		update_option( DB_Schema::OPTION_KEY, '9.9.9' );

		DB_Schema::install();

		$this->assertSame( array(), $this->dbdelta_calls() );
		$this->assertSame( '9.9.9', get_option( DB_Schema::OPTION_KEY ) );
	}

	public function test_create_table_can_be_called_repeatedly(): void {
		Image_Audit_Repository::create_table();
		Image_Audit_Repository::create_table();

		$this->assertCount( 2, $this->dbdelta_calls() );
		$this->assertSame( $this->dbdelta_calls()[0], $this->dbdelta_calls()[1] );
	}

	public function test_the_table_uses_the_wordpress_prefix(): void {
		$this->assertSame( 'wptest_bbai_image_audit', Image_Audit_Repository::table() );
	}

	public function test_the_schema_declares_the_indexes_the_issue_table_relies_on(): void {
		Image_Audit_Repository::create_table();
		$sql = $this->dbdelta_calls()[0];

		$this->assertStringContainsString( 'UNIQUE KEY attachment_site (attachment_id, site_id)', $sql );
		$this->assertStringContainsString( 'KEY severity_status (severity, audit_status)', $sql );
		$this->assertStringContainsString( 'KEY audit_status (audit_status)', $sql );
		$this->assertStringContainsString( 'KEY last_scanned_at (last_scanned_at)', $sql );
		$this->assertStringContainsString( 'KEY run_id (run_id)', $sql );
		$this->assertStringContainsString( 'DEFAULT CHARACTER SET utf8mb4', $sql );
	}

	public function test_the_schema_stores_no_image_binary_data(): void {
		Image_Audit_Repository::create_table();
		$sql = strtolower( $this->dbdelta_calls()[0] );

		$this->assertStringNotContainsString( 'blob', $sql );
		$this->assertStringNotContainsString( 'longtext', $sql );
	}

	public function test_status_helpers(): void {
		$this->assertTrue( Image_Audit_Repository::is_valid_status( Image_Audit_Repository::STATUS_OPEN ) );
		$this->assertFalse( Image_Audit_Repository::is_valid_status( 'nonsense' ) );

		$this->assertNotContains( Image_Audit_Repository::STATUS_OPEN, Image_Audit_Repository::settled_statuses() );
		$this->assertContains( Image_Audit_Repository::STATUS_DECORATIVE, Image_Audit_Repository::settled_statuses() );
		$this->assertContains( Image_Audit_Repository::STATUS_RESOLVED, Image_Audit_Repository::settled_statuses() );
		$this->assertContains( Image_Audit_Repository::STATUS_DISMISSED, Image_Audit_Repository::settled_statuses() );
	}
}

/**
 * Minimal $wpdb double covering only what the schema code touches.
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class BBAI_Test_WPDB {

	public $prefix = 'wptest_';

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
	}
}
