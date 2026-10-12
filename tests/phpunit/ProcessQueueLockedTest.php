<?php
/**
 * Calls the real Core::process_queue() for the generation_in_progress branch:
 * real Queue SQL against an SQLite-backed $wpdb that treats attempts as
 * UNSIGNED (an out-of-range `attempts - 1` errors, like MySQL strict mode),
 * the real Generation_Lock with an injected clock/sleeper, and a backend spy.
 */
declare(strict_types=1);

namespace {
	use BeepBeepAI\AltTextGenerator\Core;
	use BeepBeepAI\AltTextGenerator\Generation_Lock;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
	use PHPUnit\Framework\TestCase;

	#[RunTestsInSeparateProcesses]
	final class ProcessQueueLockedTest extends TestCase {
		const IMG = 77;
		const ALT = '_wp_attachment_image_alt';

		/** @var Core */
		private $core;

		/** @var object */
		private $backend;

		/** @var float */
		private $fake_now = 1000.0;

		/** @var int */
		private $slept_us = 0;

		protected function setUp(): void {
			require __DIR__ . '/stubs-process-queue.php';
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';

			$GLOBALS['wpdb'] = new BBAI_Test_Sqlite_Wpdb();
			$GLOBALS['wpdb']->pdo->exec(
				'CREATE TABLE wp_bbai_queue (id INTEGER PRIMARY KEY, attachment_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT "pending",
				attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts >= 0), source TEXT NOT NULL DEFAULT "auto", last_error TEXT NULL,
				enqueued_at TEXT NOT NULL, locked_at TEXT NULL, completed_at TEXT NULL)'
			);
			$GLOBALS['bbai_test_meta'][ self::IMG ] = array();

			// Backend spy: any call into the API client is a backend call.
			$this->backend = new class() {
				public $calls = array();
				public function __call( $name, $args ) { $this->calls[] = $name; return new \WP_Error( 'spy', 'spy' ); }
			};
			$ref        = new \ReflectionClass( Core::class );
			$this->core = $ref->newInstanceWithoutConstructor();
			$prop       = $ref->getProperty( 'api_client' );
			$prop->setAccessible( true );
			$prop->setValue( $this->core, $this->backend );

			// Inject the wait: a fake wall clock that the sleeper advances.
			Generation_Lock::$clock   = function () { return $this->fake_now; };
			Generation_Lock::$sleeper = function ( $usec ) { $this->slept_us += (int) $usec; $this->fake_now += $usec / 1e6; };

			// Another worker holds the image for the whole run.
			$this->assertTrue( Generation_Lock::acquire( self::IMG ) );
		}

		protected function tearDown(): void {
			Generation_Lock::$clock   = null;
			Generation_Lock::$sleeper = null;
		}

		private function enqueue( int $attempts ): int {
			$GLOBALS['wpdb']->pdo->exec( "INSERT INTO wp_bbai_queue (attachment_id, status, attempts, source, enqueued_at) VALUES (" . self::IMG . ", 'pending', {$attempts}, 'auto', '2026-10-12 10:00:00')" );
			return (int) $GLOBALS['wpdb']->pdo->lastInsertId();
		}

		private function row( int $id ): array {
			return $GLOBALS['wpdb']->pdo->query( "SELECT * FROM wp_bbai_queue WHERE id = {$id}" )->fetch( \PDO::FETCH_ASSOC );
		}

		public static function starting_attempts(): array {
			return array( 'attempts 0' => array( 0 ), 'attempts 1' => array( 1 ), 'attempts 3' => array( 3 ) );
		}

		#[DataProvider( 'starting_attempts' )]
		public function test_locked_item_is_deferred_without_using_attempts( int $start ): void {
			$id = $this->enqueue( $start );
			$this->core->process_queue();

			$row = $this->row( $id );
			$this->assertSame( $start, (int) $row['attempts'], 'deferral must refund the attempt claim_batch added' );
			$this->assertSame( 'pending', $row['status'] );
			$this->assertNull( $row['locked_at'] );
			$this->assertStringStartsWith( 'generation_in_progress#1:', (string) $row['last_error'] );
			$this->assertSame( array(), $this->backend->calls, 'backend must not be called while the image is locked' );
			$this->assertSame( array(), $GLOBALS['wpdb']->errors, 'queue SQL must not error' );
			$this->assertTrue( Generation_Lock::is_held( self::IMG ), 'holder keeps its lock' );
		}

		public function test_locked_item_stays_pending_across_repeated_runs(): void {
			$id = $this->enqueue( 0 );
			for ( $i = 1; $i <= 3; $i++ ) {
				$this->core->process_queue();
				$row = $this->row( $id );
				$this->assertSame( 'pending', $row['status'] );
				$this->assertSame( 0, (int) $row['attempts'] );
				$this->assertStringStartsWith( "generation_in_progress#{$i}:", (string) $row['last_error'] );
			}
			$this->assertSame( array(), $this->backend->calls );
			$this->assertNotEmpty( $GLOBALS['bbai_test_scheduled'], 'a retry run is scheduled' );
		}

		/**
		 * Through process_queue() claim_batch always adds 1 first, so the decrement
		 * normally starts at 1 or more. This covers the one way it can start at 0:
		 * the row's attempts is zeroed by another process while this run waits on
		 * the lock. With UNSIGNED attempts the old GREATEST(attempts - 1, 0) errors
		 * there and leaves the job stuck in 'processing'.
		 */
		public function test_deferral_floors_at_zero_when_row_reset_during_wait(): void {
			$id    = $this->enqueue( 0 );
			$pdo   = $GLOBALS['wpdb']->pdo;
			$inner = Generation_Lock::$sleeper;
			Generation_Lock::$sleeper = function ( $usec ) use ( $inner, $pdo, $id ) {
				$pdo->exec( "UPDATE wp_bbai_queue SET attempts = 0 WHERE id = {$id}" );
				$inner( $usec );
			};
			$this->core->process_queue();

			$row = $this->row( $id );
			$this->assertSame( array(), $GLOBALS['wpdb']->errors, 'decrement must not go out of range' );
			$this->assertSame( 'pending', $row['status'] );
			$this->assertSame( 0, (int) $row['attempts'] );
			$this->assertSame( array(), $this->backend->calls );
		}

		public function test_waiter_gives_up_after_25s_wall_clock(): void {
			$this->enqueue( 1 );
			$real = microtime( true );
			$this->core->process_queue();
			$this->assertSame( 25000, Generation_Lock::DEFAULT_WAIT_MS );
			$this->assertSame( 25000 * 1000, $this->slept_us, 'waits exactly the 25 s budget' );
			$this->assertEqualsWithDelta( 1025.0, $this->fake_now, 0.0001 );
			$this->assertLessThan( 5.0, microtime( true ) - $real, 'injected sleeper, no real sleeping' );
		}

		public function test_saved_alt_completes_next_run_without_backend(): void {
			$id = $this->enqueue( 1 );
			$this->core->process_queue();
			$this->assertSame( 'pending', $this->row( $id )['status'] );

			// The holder saves alt text (from another process) and releases.
			$GLOBALS['bbai_test_meta'][ self::IMG ][ self::ALT ] = 'A red bicycle';
			Generation_Lock::release( self::IMG );
			$slept_before = $this->slept_us;

			$this->core->process_queue();
			$row = $this->row( $id );
			$this->assertSame( 'completed', $row['status'] );
			$this->assertNull( $row['last_error'] );
			$this->assertSame( 2, (int) $row['attempts'], 'claim_batch counted the finishing run; nothing else touched attempts' );
			$this->assertSame( array(), $this->backend->calls, 'no backend call once alt text exists' );
			$this->assertSame( $slept_before, $this->slept_us, 'no lock wait on the skip path' );
			$this->assertSame( 'A red bicycle', get_post_meta( self::IMG, self::ALT, true ) );
		}
	}
}
