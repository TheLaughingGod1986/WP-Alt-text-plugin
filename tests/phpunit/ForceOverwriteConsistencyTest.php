<?php
/**
 * force_overwrite means exactly true everywhere: queueing a media change, and
 * the queue worker, agree. Explicit regenerate jobs keep overwriting.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Core;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class ForceOverwriteConsistencyTest extends TestCase {
		const IMG = 91;
		const ALT = '_wp_attachment_image_alt';

		/** @var Core */
		private $core;

		protected function setUp(): void {
			require __DIR__ . '/stubs-process-queue.php';
			if ( ! function_exists( 'bbai_is_authenticated' ) ) {
				function bbai_is_authenticated() {
					return true;
				}
			}
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';
			require_once __DIR__ . '/fixtures-recording-core.php';

			if ( ! class_exists( 'BBAI_Test_Sqlite_Wpdb_Insert', false ) ) {
				// Queue::enqueue() inserts; the shared SQLite stub only reads and updates.
				eval(
					'class BBAI_Test_Sqlite_Wpdb_Insert extends BBAI_Test_Sqlite_Wpdb {
						public $insert_id = 0;
						public function insert( $table, $data, $format = null ) {
							$cols = array_keys( $data );
							$st   = $this->pdo->prepare( "INSERT INTO {$table} (" . implode( ",", $cols ) . ") VALUES (" . implode( ",", array_fill( 0, count( $cols ), "?" ) ) . ")" );
							$ok   = $st->execute( array_values( $data ) );
							$this->insert_id = (int) $this->pdo->lastInsertId();
							return $ok ? 1 : false;
						}
					}'
				);
			}
			$GLOBALS['wpdb'] = new BBAI_Test_Sqlite_Wpdb_Insert();
			$GLOBALS['wpdb']->pdo->exec(
				'CREATE TABLE wp_bbai_queue (id INTEGER PRIMARY KEY, attachment_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT "pending",
				attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts >= 0), source TEXT NOT NULL DEFAULT "auto", last_error TEXT NULL,
				enqueued_at TEXT NOT NULL, locked_at TEXT NULL, completed_at TEXT NULL)'
			);
			$GLOBALS['bbai_test_meta'][ self::IMG ] = array();
			unset( $GLOBALS['bbai_test_options'][ Core::OPTION_KEY ] );

			// A plan that includes upload generation, so queueing reaches the overwrite check.
			$backend = new class() {
				public function get_license_data() { return array( 'organization' => array( 'plan' => 'pro' ) ); }
				public function __call( $name, $args ) { return new \WP_Error( 'spy', 'spy' ); }
			};
			$ref        = new \ReflectionClass( BBAI_Test_Recording_Core::class );
			$this->core = $ref->newInstanceWithoutConstructor();
			$prop       = ( new \ReflectionClass( Core::class ) )->getProperty( 'api_client' );
			$prop->setAccessible( true );
			$prop->setValue( $this->core, $backend );
		}

		private function queue_attachment( string $source ) {
			$m = new \ReflectionMethod( Core::class, 'queue_attachment' );
			$m->setAccessible( true );
			return $m->invoke( $this->core, self::IMG, $source );
		}

		private function queued_rows(): array {
			return $GLOBALS['wpdb']->pdo->query( 'SELECT * FROM wp_bbai_queue' )->fetchAll( \PDO::FETCH_ASSOC );
		}

		public static function non_true_values(): array {
			return array(
				'string 1'   => array( '1' ),
				'int 1'      => array( 1 ),
				'string yes' => array( 'yes' ),
				'false'      => array( false ),
				'missing'    => array( null ),
			);
		}

		#[DataProvider( 'non_true_values' )]
		public function test_helper_accepts_only_exactly_true( $value ): void {
			$opts = null === $value ? array() : array( 'force_overwrite' => $value );
			$this->assertFalse( Core::force_overwrite_enabled( $opts ) );
			$this->assertTrue( Core::force_overwrite_enabled( array( 'force_overwrite' => true ) ) );
			$this->assertFalse( Core::force_overwrite_enabled( 'garbage' ) );
		}

		#[DataProvider( 'non_true_values' )]
		public function test_queueing_and_worker_agree_for_non_true_values( $value ): void {
			$GLOBALS['bbai_test_options'][ Core::OPTION_KEY ] = null === $value ? array() : array( 'force_overwrite' => $value );
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'Written by the editor';

			$this->assertFalse( (bool) $this->queue_attachment( 'metadata' ), 'queueing does not overwrite' );
			$this->assertSame( array(), $this->queued_rows() );
			$this->assertTrue( Core::queue_job_should_skip_existing_alt( 'metadata', 'Written by the editor', get_option( Core::OPTION_KEY, array() ) ), 'worker skips too' );
		}

		public function test_queueing_and_worker_agree_for_true(): void {
			$GLOBALS['bbai_test_options'][ Core::OPTION_KEY ] = array( 'force_overwrite' => true );
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'Old alt text';

			$this->assertNotFalse( $this->queue_attachment( 'metadata' ), 'queued for overwrite' );
			$this->assertCount( 1, $this->queued_rows() );

			$this->core->process_queue();
			$this->assertSame( array( self::IMG ), $this->core->generated, 'worker overwrites too' );
		}

		public function test_image_without_alt_is_queued_whatever_the_setting(): void {
			$GLOBALS['bbai_test_options'][ Core::OPTION_KEY ] = array( 'force_overwrite' => '1' );
			$this->assertNotFalse( $this->queue_attachment( 'metadata' ) );
			$this->assertCount( 1, $this->queued_rows() );
		}

		public function test_bulk_regenerate_still_overwrites_without_force_overwrite(): void {
			$GLOBALS['bbai_test_options'][ Core::OPTION_KEY ] = array( 'force_overwrite' => false );
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'Old alt text';
			$GLOBALS['wpdb']->pdo->exec( "INSERT INTO wp_bbai_queue (attachment_id, status, attempts, source, enqueued_at) VALUES (" . self::IMG . ", 'pending', 0, 'bulk-regenerate', '2026-10-29 10:00:00')" );

			$this->core->process_queue();

			$this->assertSame( array( self::IMG ), $this->core->generated );
			$this->assertSame( 'Regenerated alt', get_post_meta( self::IMG, self::ALT, true ) );
		}
	}
}
