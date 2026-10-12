<?php
/**
 * Record building, upsert shape, and issue-code matching.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Image_Accessibility_Score_Service;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Repository;
use PHPUnit\Framework\TestCase;

final class ImageAuditRepositoryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdb']              = new BBAI_Fake_WPDB();
		$GLOBALS['bbai_test_options'] = array( 'beepbeepai_site_id' => 'test_site_install_id_0123456789ab' );

		remove_all_filters( 'bbai_audit_score_weights' );
	}

	/**
	 * @param array<string,mixed> $overrides Context overrides.
	 */
	private function context( array $overrides = array() ): Image_Audit_Context {
		return new Image_Audit_Context(
			array_merge(
				array(
					'attachment_id'   => 42,
					'alt'             => 'A grey heron standing in shallow water',
					'context_summary' => 'Attached to: About Us',
					'parent_post_id'  => 7,
				),
				$overrides
			)
		);
	}

	/* ─── Record building ─── */

	public function test_an_image_with_issues_is_recorded_as_open(): void {
		$record = Image_Audit_Repository::build_record(
			$this->context( array( 'alt' => null ) ),
			array(
				'codes'    => array( Audit_Issue::MISSING_ALT ),
				'severity' => Audit_Issue::SEVERITY_CRITICAL,
			),
			'run-1'
		);

		$this->assertSame( Image_Audit_Repository::STATUS_OPEN, $record['audit_status'] );
		$this->assertSame( ',missing_alt,', $record['issue_codes'] );
		$this->assertSame( 1, $record['issue_count'] );
		$this->assertSame( Audit_Issue::SEVERITY_CRITICAL, $record['severity'] );
		$this->assertSame( 'run-1', $record['run_id'] );
	}

	public function test_an_image_with_no_issues_is_recorded_as_resolved(): void {
		$record = Image_Audit_Repository::build_record(
			$this->context(),
			array(
				'codes'    => array(),
				'severity' => Audit_Issue::SEVERITY_INFORMATION,
			),
			'run-1'
		);

		$this->assertSame( Image_Audit_Repository::STATUS_RESOLVED, $record['audit_status'] );
		$this->assertSame( '', $record['issue_codes'] );
		$this->assertSame( 0, $record['issue_count'] );
	}

	public function test_a_decorative_image_is_recorded_as_settled(): void {
		$record = Image_Audit_Repository::build_record(
			$this->context( array( 'alt' => '', 'is_decorative' => true ) ),
			array(
				'codes'    => array(),
				'severity' => Audit_Issue::SEVERITY_INFORMATION,
			),
			'run-1'
		);

		$this->assertSame( Image_Audit_Repository::STATUS_DECORATIVE, $record['audit_status'] );
		$this->assertSame( 0.0, $record['score_contribution'] );
	}

	public function test_an_invalid_severity_falls_back_to_information(): void {
		$record = Image_Audit_Repository::build_record(
			$this->context(),
			array(
				'codes'    => array( Audit_Issue::MISSING_ALT ),
				'severity' => 'nonsense',
			),
			'run-1'
		);

		$this->assertSame( Audit_Issue::SEVERITY_INFORMATION, $record['severity'] );
	}

	public function test_the_record_carries_the_alt_hash_and_length(): void {
		$context = $this->context();
		$record  = Image_Audit_Repository::build_record( $context, array( 'codes' => array() ), 'run-1' );

		$this->assertSame( $context->get_alt_hash(), $record['current_alt_hash'] );
		$this->assertSame( $context->get_alt_length(), $record['alt_length'] );
	}

	public function test_context_and_parent_are_stored_for_display(): void {
		$record = Image_Audit_Repository::build_record( $this->context(), array( 'codes' => array() ), 'run-1' );

		$this->assertSame( 'Attached to: About Us', $record['context_summary'] );
		$this->assertSame( 7, $record['parent_post_id'] );
	}

	/* ─── Score contribution ─── */

	public function test_score_contribution_mirrors_the_severity_weights(): void {
		$weights = Image_Accessibility_Score_Service::get_weights();

		$this->assertSame(
			(float) $weights[ Audit_Issue::SEVERITY_CRITICAL ],
			Image_Audit_Repository::score_contribution( Image_Audit_Repository::STATUS_OPEN, Audit_Issue::SEVERITY_CRITICAL )
		);

		$this->assertSame(
			(float) $weights[ Audit_Issue::SEVERITY_WARNING ],
			Image_Audit_Repository::score_contribution( Image_Audit_Repository::STATUS_OPEN, Audit_Issue::SEVERITY_WARNING )
		);
	}

	public function test_settled_rows_contribute_nothing_to_the_score(): void {
		foreach ( Image_Audit_Repository::settled_statuses() as $status ) {
			$this->assertSame(
				0.0,
				Image_Audit_Repository::score_contribution( $status, Audit_Issue::SEVERITY_CRITICAL ),
				$status . ' must not be penalised'
			);
		}
	}

	/* ─── Upsert ─── */

	public function test_many_records_are_written_in_one_statement(): void {
		$records = array();
		foreach ( array( 10, 20, 30 ) as $id ) {
			$records[] = Image_Audit_Repository::build_record(
				$this->context( array( 'attachment_id' => $id ) ),
				array( 'codes' => array() ),
				'run-1'
			);
		}

		Image_Audit_Repository::upsert_many( $records );

		$this->assertCount( 1, $GLOBALS['wpdb']->queries );

		$query = $GLOBALS['wpdb']->last_query();
		$this->assertStringContainsString( 'INSERT INTO `wptest_bbai_image_audit`', $query );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', $query );
		$this->assertSame( 3, substr_count( $query, "'test_site_install_id_0123456789ab'" ) );
	}

	public function test_an_empty_record_set_touches_the_database_not_at_all(): void {
		$this->assertSame( 0, Image_Audit_Repository::upsert_many( array() ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	public function test_records_without_an_attachment_id_are_dropped(): void {
		$record                  = Image_Audit_Repository::build_record( $this->context(), array( 'codes' => array() ), 'run-1' );
		$record['attachment_id'] = 0;

		$this->assertSame( 0, Image_Audit_Repository::upsert_many( array( $record ) ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	public function test_settled_rows_get_a_resolved_timestamp_and_open_rows_do_not(): void {
		$open = Image_Audit_Repository::build_record(
			$this->context( array( 'alt' => null ) ),
			array(
				'codes'    => array( Audit_Issue::MISSING_ALT ),
				'severity' => Audit_Issue::SEVERITY_CRITICAL,
			),
			'run-1'
		);

		Image_Audit_Repository::upsert_many( array( $open ) );
		$this->assertStringContainsString( 'NULL', $GLOBALS['wpdb']->last_query() );

		$GLOBALS['wpdb']->reset();

		$resolved = Image_Audit_Repository::build_record( $this->context(), array( 'codes' => array() ), 'run-1' );
		Image_Audit_Repository::upsert_many( array( $resolved ) );

		$this->assertStringNotContainsString( 'NULL', $GLOBALS['wpdb']->last_query() );
	}

	/* ─── Reads ─── */

	public function test_issue_counts_match_on_the_delimited_code(): void {
		$this->prime_table_exists();
		$GLOBALS['wpdb']->var_result = 12;

		$this->assertSame( 12, Image_Audit_Repository::count_by_issue( Audit_Issue::ALT_TOO_LONG ) );

		$query = $GLOBALS['wpdb']->last_query();

		// Two things matter here. The code is wrapped in commas, so a search cannot
		// match a different code that merely shares a prefix; and the underscores
		// are backslash-escaped, so they cannot act as single-character LIKE
		// wildcards. The exact number of backslashes is a quoting detail.
		$this->assertMatchesRegularExpression( '/,alt\\\\*_too\\\\*_long,/', $query );
		$this->assertMatchesRegularExpression( "/LIKE '%,.+,%'/", $query );
	}

	public function test_unknown_issue_codes_are_never_queried(): void {
		$this->prime_table_exists();
		$GLOBALS['wpdb']->reset();

		$this->assertSame( 0, Image_Audit_Repository::count_by_issue( 'bogus_code' ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	public function test_counters_are_grouped_by_severity_and_status(): void {
		$this->prime_table_exists();

		$GLOBALS['wpdb']->results_result = array(
			array(
				'severity'     => Audit_Issue::SEVERITY_CRITICAL,
				'audit_status' => Image_Audit_Repository::STATUS_OPEN,
				'cnt'          => '10',
			),
			array(
				'severity'     => Audit_Issue::SEVERITY_WARNING,
				'audit_status' => Image_Audit_Repository::STATUS_OPEN,
				'cnt'          => '5',
			),
			array(
				'severity'     => Audit_Issue::SEVERITY_INFORMATION,
				'audit_status' => Image_Audit_Repository::STATUS_DECORATIVE,
				'cnt'          => '3',
			),
			array(
				'severity'     => Audit_Issue::SEVERITY_INFORMATION,
				'audit_status' => Image_Audit_Repository::STATUS_RESOLVED,
				'cnt'          => '2',
			),
		);

		$counters = Image_Audit_Repository::get_counters();

		$this->assertSame( 20, $counters['scanned_images'] );
		$this->assertSame( 10, $counters['critical'] );
		$this->assertSame( 5, $counters['warning'] );
		$this->assertSame( 15, $counters['open'] );
		$this->assertSame( 5, $counters['settled'], 'Decorative and resolved rows are settled, not outstanding' );
	}

	public function test_counters_use_a_single_grouped_query(): void {
		$this->prime_table_exists();
		$GLOBALS['wpdb']->reset();
		$GLOBALS['wpdb']->results_result = array();

		Image_Audit_Repository::get_counters();

		$this->assertCount( 1, $GLOBALS['wpdb']->queries );
		$this->assertStringContainsString( 'GROUP BY severity, audit_status', $GLOBALS['wpdb']->last_query() );
	}

	public function test_reads_are_scoped_to_this_site(): void {
		$this->prime_table_exists();
		$GLOBALS['wpdb']->reset();
		$GLOBALS['wpdb']->results_result = array();

		Image_Audit_Repository::get_counters();

		$this->assertStringContainsString( "site_id = 'test_site_install_id_0123456789ab'", $GLOBALS['wpdb']->last_query() );
	}

	public function test_deleting_a_row_is_scoped_to_attachment_and_site(): void {
		$this->prime_table_exists();

		Image_Audit_Repository::delete_row( 42 );

		$this->assertCount( 1, $GLOBALS['wpdb']->deletes );
		$this->assertSame( 42, $GLOBALS['wpdb']->deletes[0]['where']['attachment_id'] );
		$this->assertArrayHasKey( 'site_id', $GLOBALS['wpdb']->deletes[0]['where'] );
	}

	public function test_deleting_an_invalid_id_does_nothing(): void {
		$this->prime_table_exists();

		$this->assertFalse( Image_Audit_Repository::delete_row( 0 ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->deletes );
	}

	/* ─── Status changes ─── */

	public function test_dismissing_a_row_settles_it_and_zeroes_its_score(): void {
		$this->prime_table_exists();

		$this->assertTrue( Image_Audit_Repository::set_status( 42, Image_Audit_Repository::STATUS_DISMISSED ) );

		$update = $GLOBALS['wpdb']->updates[0];

		$this->assertSame( Image_Audit_Repository::STATUS_DISMISSED, $update['data']['audit_status'] );
		$this->assertSame( 0.0, $update['data']['score_contribution'] );
		$this->assertNotNull( $update['data']['resolved_at'] );
		$this->assertSame( 42, $update['where']['attachment_id'] );
		$this->assertArrayHasKey( 'site_id', $update['where'] );
	}

	public function test_reopening_a_row_clears_its_resolution_timestamp(): void {
		$this->prime_table_exists();

		Image_Audit_Repository::set_status( 42, Image_Audit_Repository::STATUS_OPEN );

		$update = $GLOBALS['wpdb']->updates[0];

		$this->assertSame( Image_Audit_Repository::STATUS_OPEN, $update['data']['audit_status'] );
		$this->assertNull( $update['data']['resolved_at'] );
	}

	public function test_an_unknown_status_is_refused(): void {
		$this->prime_table_exists();

		$this->assertFalse( Image_Audit_Repository::set_status( 42, 'nonsense' ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->updates );
	}

	public function test_an_invalid_id_is_refused(): void {
		$this->prime_table_exists();

		$this->assertFalse( Image_Audit_Repository::set_status( 0, Image_Audit_Repository::STATUS_DISMISSED ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->updates );
	}

	public function test_a_failed_write_is_reported(): void {
		$this->prime_table_exists();
		$GLOBALS['wpdb']->update_result = false;

		$this->assertFalse( Image_Audit_Repository::set_status( 42, Image_Audit_Repository::STATUS_DISMISSED ) );
	}

	/* ─── Distinct issue counts ─── */

	public function test_weak_alt_counts_distinct_images_not_findings(): void {
		$this->prime_table_exists();
		$GLOBALS['wpdb']->var_result = 12;

		$count = Image_Audit_Repository::count_by_any_issue(
			array( Audit_Issue::FILENAME_AS_ALT, Audit_Issue::ALT_TOO_SHORT )
		);

		$this->assertSame( 12, $count );

		// An image carrying both codes is one image with weak alt text, not two.
		$this->assertStringContainsString( 'COUNT(DISTINCT attachment_id)', $GLOBALS['wpdb']->last_query() );
		$this->assertSame( 2, substr_count( $GLOBALS['wpdb']->last_query(), 'issue_codes LIKE' ) );
	}

	public function test_counting_no_codes_needs_no_query(): void {
		$this->prime_table_exists();
		$GLOBALS['wpdb']->reset();

		$this->assertSame( 0, Image_Audit_Repository::count_by_any_issue( array() ) );
		$this->assertSame( 0, Image_Audit_Repository::count_by_any_issue( array( 'made_up_code' ) ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	/**
	 * Make the repository believe its table exists.
	 *
	 * DB_Schema::table_exists() runs SHOW TABLES LIKE; a non-empty get_var()
	 * satisfies it, and the force-refresh argument bypasses the per-request cache.
	 */
	private function prime_table_exists(): void {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-db.php';

		$GLOBALS['wpdb']->var_result = 'wptest_bbai_image_audit';
		Image_Audit_Repository::table_exists( true );
		$GLOBALS['wpdb']->reset();
	}
}
