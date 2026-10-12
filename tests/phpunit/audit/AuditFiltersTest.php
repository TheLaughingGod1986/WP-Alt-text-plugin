<?php
/**
 * Filter definitions and the SQL they drive.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Filters;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Repository;
use PHPUnit\Framework\TestCase;

final class AuditFiltersTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdb']              = new BBAI_Fake_WPDB();
		$GLOBALS['bbai_test_options'] = array( 'beepbeepai_site_id' => 'test_site_install_id_0123456789ab' );

		remove_all_filters( 'bbai_audit_filters' );

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-db.php';
		$GLOBALS['wpdb']->var_result = 'wptest_bbai_image_audit';
		Image_Audit_Repository::table_exists( true );
		$GLOBALS['wpdb']->reset();
	}

	protected function tearDown(): void {
		remove_all_filters( 'bbai_audit_filters' );
	}

	/* ─── Definitions ─── */

	public function test_the_brief_filters_are_all_present(): void {
		$slugs = Audit_Filters::slugs();

		foreach ( array( 'all', 'missing_alt', 'weak_alt', 'duplicate_alt', 'review_required', 'critical', 'warning', 'resolved' ) as $expected ) {
			$this->assertContains( $expected, $slugs, $expected . ' filter missing' );
		}
	}

	public function test_every_filter_has_a_label_and_description(): void {
		foreach ( Audit_Filters::all() as $slug => $definition ) {
			$this->assertNotSame( '', (string) $definition['label'], $slug );
			$this->assertNotSame( '', (string) $definition['description'], $slug );
		}
	}

	public function test_unknown_filters_fall_back_to_all(): void {
		$this->assertSame( Audit_Filters::ALL, Audit_Filters::sanitize( 'nonsense' ) );
		$this->assertSame( Audit_Filters::ALL, Audit_Filters::sanitize( '' ) );
		$this->assertSame( Audit_Filters::ALL, Audit_Filters::sanitize( null ) );
		$this->assertSame( Audit_Filters::MISSING_ALT, Audit_Filters::sanitize( 'missing_alt' ) );
	}

	public function test_review_required_codes_come_from_the_catalogue(): void {
		$codes = Audit_Filters::review_required_codes();

		$this->assertContains( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED, $codes );
		$this->assertContains( Audit_Issue::DUPLICATE_ALT, $codes );
		$this->assertNotContains( Audit_Issue::MISSING_ALT, $codes, 'missing alt is auto-fixable, not a judgement call' );

		// Every listed code really does declare requires_review.
		foreach ( $codes as $code ) {
			$this->assertTrue( (bool) Audit_Issue::definition( $code )['requires_review'], $code );
		}
	}

	public function test_weak_alt_covers_the_content_quality_codes_only(): void {
		$codes = Audit_Filters::weak_alt_codes();

		$this->assertContains( Audit_Issue::FILENAME_AS_ALT, $codes );
		$this->assertContains( Audit_Issue::GENERIC_ALT, $codes );
		$this->assertContains( Audit_Issue::ALT_TOO_SHORT, $codes );
		$this->assertContains( Audit_Issue::POSSIBLE_KEYWORD_STUFFING, $codes );
		$this->assertNotContains( Audit_Issue::MISSING_ALT, $codes );
		$this->assertNotContains( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED, $codes );
	}

	public function test_filters_are_extensible(): void {
		add_filter(
			'bbai_audit_filters',
			static function ( $filters ) {
				$filters['custom'] = array(
					'label'       => 'Custom',
					'description' => 'A custom filter.',
				);
				return $filters;
			}
		);

		$this->assertContains( 'custom', Audit_Filters::slugs() );
		$this->assertTrue( Audit_Filters::is_valid( 'custom' ) );
	}

	/* ─── Query construction ─── */

	public function test_missing_alt_matches_the_delimited_code_on_open_rows(): void {
		$GLOBALS['wpdb']->var_result = 0;

		Image_Audit_Repository::query_issues( array( 'filter' => Audit_Filters::MISSING_ALT ) );
		$query = $GLOBALS['wpdb']->last_query();

		$this->assertStringContainsString( "audit_status = 'open'", $query );
		$this->assertMatchesRegularExpression( '/issue_codes LIKE \'%,missing\\\\*_alt,%\'/', $query );
	}

	public function test_weak_alt_matches_any_of_its_codes(): void {
		$GLOBALS['wpdb']->var_result = 0;

		Image_Audit_Repository::query_issues( array( 'filter' => Audit_Filters::WEAK_ALT ) );
		$query = $GLOBALS['wpdb']->last_query();

		$this->assertSame( 4, substr_count( $query, 'issue_codes LIKE' ), 'weak alt is an OR over four codes' );
		$this->assertStringContainsString( ' OR ', $query );
	}

	public function test_severity_filters_query_severity_directly(): void {
		$GLOBALS['wpdb']->var_result = 0;

		Image_Audit_Repository::query_issues( array( 'filter' => Audit_Filters::CRITICAL ) );
		$this->assertStringContainsString( "severity = 'critical'", $GLOBALS['wpdb']->last_query() );

		Image_Audit_Repository::query_issues( array( 'filter' => Audit_Filters::WARNING ) );
		$this->assertStringContainsString( "severity = 'warning'", $GLOBALS['wpdb']->last_query() );
	}

	public function test_resolved_covers_every_settled_status(): void {
		$GLOBALS['wpdb']->var_result = 0;

		Image_Audit_Repository::query_issues( array( 'filter' => Audit_Filters::RESOLVED ) );
		$query = $GLOBALS['wpdb']->last_query();

		foreach ( Image_Audit_Repository::settled_statuses() as $status ) {
			$this->assertStringContainsString( "'" . $status . "'", $query, $status . ' missing from resolved filter' );
		}
	}

	public function test_the_default_filter_excludes_rows_with_no_findings(): void {
		$GLOBALS['wpdb']->var_result = 0;

		Image_Audit_Repository::query_issues( array( 'filter' => Audit_Filters::ALL ) );
		$query = $GLOBALS['wpdb']->last_query();

		$this->assertStringContainsString( "audit_status = 'open'", $query );
		$this->assertStringContainsString( "issue_codes <> ''", $query );
	}

	public function test_every_query_is_scoped_to_this_site(): void {
		$GLOBALS['wpdb']->var_result = 0;

		foreach ( Audit_Filters::slugs() as $slug ) {
			$GLOBALS['wpdb']->reset();
			Image_Audit_Repository::query_issues( array( 'filter' => $slug ) );

			$this->assertStringContainsString(
				"site_id = 'test_site_install_id_0123456789ab'",
				$GLOBALS['wpdb']->last_query(),
				$slug . ' is not site-scoped'
			);
		}
	}

	/* ─── Pagination ─── */

	public function test_an_empty_result_set_costs_one_count_query(): void {
		$GLOBALS['wpdb']->var_result = 0;

		$result = Image_Audit_Repository::query_issues( array( 'filter' => Audit_Filters::ALL ) );

		$this->assertSame( array(), $result['rows'] );
		$this->assertSame( 0, $result['total'] );
		$this->assertCount( 1, $GLOBALS['wpdb']->queries, 'no row query when the count is zero' );
	}

	public function test_pagination_limits_and_offsets(): void {
		$GLOBALS['wpdb']->var_result     = 95;
		$GLOBALS['wpdb']->results_result = array();

		$result = Image_Audit_Repository::query_issues(
			array(
				'filter'   => Audit_Filters::ALL,
				'page'     => 3,
				'per_page' => 20,
			)
		);

		$this->assertSame( 95, $result['total'] );
		$this->assertSame( 5, $result['total_pages'] );
		$this->assertSame( 3, $result['page'] );
		$this->assertStringContainsString( 'LIMIT 20 OFFSET 40', $GLOBALS['wpdb']->last_query() );
	}

	public function test_a_page_beyond_the_end_clamps_to_the_last_page(): void {
		$GLOBALS['wpdb']->var_result     = 25;
		$GLOBALS['wpdb']->results_result = array();

		$result = Image_Audit_Repository::query_issues(
			array(
				'page'     => 999,
				'per_page' => 20,
			)
		);

		$this->assertSame( 2, $result['page'] );
		$this->assertStringContainsString( 'OFFSET 20', $GLOBALS['wpdb']->last_query() );
	}

	public function test_per_page_is_clamped_to_a_sane_range(): void {
		$GLOBALS['wpdb']->var_result     = 500;
		$GLOBALS['wpdb']->results_result = array();

		Image_Audit_Repository::query_issues( array( 'per_page' => 100000 ) );
		$this->assertStringContainsString( 'LIMIT 100 ', $GLOBALS['wpdb']->last_query() );

		Image_Audit_Repository::query_issues( array( 'per_page' => 0 ) );
		$this->assertStringContainsString( 'LIMIT 1 ', $GLOBALS['wpdb']->last_query() );
	}

	public function test_rows_are_ordered_most_serious_first(): void {
		$GLOBALS['wpdb']->var_result     = 10;
		$GLOBALS['wpdb']->results_result = array();

		Image_Audit_Repository::query_issues( array() );
		$query = $GLOBALS['wpdb']->last_query();

		$this->assertStringContainsString( 'ORDER BY FIELD(severity', $query );
		$this->assertStringContainsString( 'DESC', $query );
		// Critical ranks last in the FIELD list, so DESC puts it first.
		$this->assertMatchesRegularExpression( "/FIELD\(severity, 'information', 'review', 'warning', 'critical'\) DESC/", $query );
	}

	/* ─── Filter counts ─── */

	public function test_all_filter_counts_come_from_a_single_query(): void {
		$GLOBALS['wpdb']->row_result = array_fill_keys( Audit_Filters::slugs(), '3' );

		$counts = Image_Audit_Repository::count_by_filter();

		$this->assertCount( 1, $GLOBALS['wpdb']->queries, 'chips must not cost one query each' );
		$this->assertSame( 3, $counts[ Audit_Filters::MISSING_ALT ] );
		$this->assertSame( 3, $counts[ Audit_Filters::RESOLVED ] );
	}

	public function test_counts_default_to_zero_when_the_query_returns_nothing(): void {
		$GLOBALS['wpdb']->row_result = null;

		$counts = Image_Audit_Repository::count_by_filter();

		foreach ( Audit_Filters::slugs() as $slug ) {
			$this->assertSame( 0, $counts[ $slug ], $slug );
		}
	}

	public function test_the_count_query_binds_every_placeholder_it_declares(): void {
		$GLOBALS['wpdb']->row_result = array();

		Image_Audit_Repository::count_by_filter();
		$query = $GLOBALS['wpdb']->last_query();

		// The SELECT is assembled from eight filter clauses, so placeholders and
		// values must line up positionally. A leftover %s or %d means they do not.
		$this->assertStringNotContainsString( '%s', $query, 'unbound string placeholder left in the query' );
		$this->assertStringNotContainsString( '%d', $query, 'unbound numeric placeholder left in the query' );
		$this->assertStringNotContainsString( "''''", $query );
	}

	public function test_every_filtered_query_binds_all_of_its_placeholders(): void {
		$GLOBALS['wpdb']->var_result     = 10;
		$GLOBALS['wpdb']->results_result = array();

		foreach ( Audit_Filters::slugs() as $slug ) {
			$GLOBALS['wpdb']->reset();
			Image_Audit_Repository::query_issues( array( 'filter' => $slug ) );

			foreach ( $GLOBALS['wpdb']->queries as $query ) {
				$this->assertStringNotContainsString( '%s', $query, $slug . ' left an unbound placeholder' );
				$this->assertStringNotContainsString( '%d', $query, $slug . ' left an unbound placeholder' );
			}
		}
	}

	public function test_counts_use_the_same_conditions_as_the_table(): void {
		$GLOBALS['wpdb']->row_result = array();

		Image_Audit_Repository::count_by_filter();
		$query = $GLOBALS['wpdb']->last_query();

		// Chips and table share one clause builder, so a chip cannot promise rows
		// the filtered table will not show.
		$this->assertStringContainsString( 'SUM(CASE WHEN', $query );
		$this->assertMatchesRegularExpression( '/issue_codes LIKE \'%,missing\\\\*_alt,%\'/', $query );
		$this->assertStringContainsString( "severity = 'critical'", $query );
	}
}
