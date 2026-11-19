<?php
/**
 * Calls the real Core::process_queue(): a job whose image gained alt text
 * after it was queued is skipped (no backend call, no credit) unless the user
 * explicitly chose regenerate or force_overwrite is exactly true.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Core;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class ProcessQueueExistingAltTest extends TestCase {
		const IMG = 88;
		const ALT = '_wp_attachment_image_alt';

		/** @var Core */
		private $core;

		/** @var object */
		private $backend;

		protected function setUp(): void {
			require __DIR__ . '/stubs-process-queue.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
			require_once __DIR__ . '/fixtures-recording-core.php';

			$GLOBALS['wpdb'] = new BBAI_Test_Sqlite_Wpdb();
			$GLOBALS['wpdb']->pdo->exec(
				'CREATE TABLE wp_bbai_queue (id INTEGER PRIMARY KEY, attachment_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT "pending",
				attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts >= 0), source TEXT NOT NULL DEFAULT "auto", last_error TEXT NULL,
				enqueued_at TEXT NOT NULL, locked_at TEXT NULL, completed_at TEXT NULL)'
			);
			$GLOBALS['bbai_test_meta'][ self::IMG ] = array();
			unset( $GLOBALS['bbai_test_options'][ Core::OPTION_KEY ] );

			$this->backend = new class() {
				public $calls = array();
				public function __call( $name, $args ) { $this->calls[] = $name; return new \WP_Error( 'spy', 'spy' ); }
			};
			// Record generation instead of running it; the skip decision happens before this call.
			$ref        = new \ReflectionClass( BBAI_Test_Recording_Core::class );
			$this->core = $ref->newInstanceWithoutConstructor();
			$prop       = ( new \ReflectionClass( Core::class ) )->getProperty( 'api_client' );
			$prop->setAccessible( true );
			$prop->setValue( $this->core, $this->backend );
		}

		private function enqueue( string $source ): int {
			$GLOBALS['wpdb']->pdo->exec( "INSERT INTO wp_bbai_queue (attachment_id, status, attempts, source, enqueued_at) VALUES (" . self::IMG . ", 'pending', 0, '{$source}', '2026-10-29 10:00:00')" );
			return (int) $GLOBALS['wpdb']->pdo->lastInsertId();
		}

		private function row( int $id ): array {
			return $GLOBALS['wpdb']->pdo->query( "SELECT * FROM wp_bbai_queue WHERE id = {$id}" )->fetch( \PDO::FETCH_ASSOC );
		}

		public function test_upload_job_preserves_alt_typed_during_the_delay(): void {
			$id = $this->enqueue( 'upload' );
			// This worker already has the (empty) meta in its object cache...
			$this->assertSame( '', get_post_meta( self::IMG, self::ALT, true ) );
			// ...then the user types alt text in another request before the job runs.
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'Typed by the editor';

			$this->core->process_queue();

			$row = $this->row( $id );
			$this->assertSame( 'completed', $row['status'] );
			$this->assertSame( 'skipped_existing_alt', $row['last_error'] );
			$this->assertSame( array(), $this->core->generated, 'no generation, so no credit spent' );
			$this->assertSame( array(), $this->backend->calls );
			wp_cache_delete( self::IMG, 'post_meta' );
			$this->assertSame( 'Typed by the editor', get_post_meta( self::IMG, self::ALT, true ) );
		}

		public static function generate_missing_sources(): array {
			return array( 'bulk' => array( 'bulk' ), 'auto' => array( 'auto' ), 'library' => array( 'library' ), 'dashboard' => array( 'dashboard' ) );
		}

		#[DataProvider( 'generate_missing_sources' )]
		public function test_generate_missing_job_skips_filled_image( string $source ): void {
			$id = $this->enqueue( $source );
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'Already described';

			$this->core->process_queue();

			$this->assertSame( 'completed', $this->row( $id )['status'] );
			$this->assertSame( 'skipped_existing_alt', $this->row( $id )['last_error'] );
			$this->assertSame( array(), $this->core->generated );
		}

		public function test_explicit_regenerate_still_generates(): void {
			$id = $this->enqueue( 'bulk-regenerate' );
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'Old alt text';

			$this->core->process_queue();

			$this->assertSame( array( self::IMG ), $this->core->generated, 'regenerate still generates and overwrites' );
			$this->assertSame( 'completed', $this->row( $id )['status'] );
			$this->assertNull( $this->row( $id )['last_error'] );
			$this->assertSame( 'Regenerated alt', get_post_meta( self::IMG, self::ALT, true ) );
		}

		public function test_force_overwrite_true_still_generates(): void {
			$GLOBALS['bbai_test_options'][ Core::OPTION_KEY ] = array( 'force_overwrite' => true );
			$this->enqueue( 'upload' );
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'Old alt text';

			$this->core->process_queue();

			$this->assertSame( array( self::IMG ), $this->core->generated );
		}

		public function test_decision_rules(): void {
			$this->assertFalse( Core::queue_job_should_skip_existing_alt( 'upload', '', array() ), 'empty alt always generates' );
			$this->assertFalse( Core::queue_job_should_skip_existing_alt( 'upload', '   ', array() ) );
			$this->assertTrue( Core::queue_job_should_skip_existing_alt( 'upload', 'x', array() ) );
			$this->assertTrue( Core::queue_job_should_skip_existing_alt( 'upload', 'x', array( 'force_overwrite' => '1' ) ), 'only exactly true overrides' );
			$this->assertTrue( Core::queue_job_should_skip_existing_alt( 'upload', 'x', 'garbage' ) );
			$this->assertFalse( Core::queue_job_should_skip_existing_alt( 'upload', 'x', array( 'force_overwrite' => true ) ) );
			$this->assertFalse( Core::queue_job_should_skip_existing_alt( 'bulk-regenerate', 'x', array() ) );
		}
	}
}
