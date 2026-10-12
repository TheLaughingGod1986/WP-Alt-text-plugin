<?php
/**
 * Discovery: row mapping, usage resolution, duplicate map, batch sizing.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Image_Discovery_Service;
use PHPUnit\Framework\TestCase;

final class ImageDiscoveryServiceTest extends TestCase {

	/** @var Image_Discovery_Service */
	private $discovery;

	protected function setUp(): void {
		$GLOBALS['wpdb']              = new BBAI_Fake_WPDB();
		$GLOBALS['bbai_test_options'] = array();
		$GLOBALS['bbai_test_post_titles'] = array();

		remove_all_filters( 'bbai_audit_batch_size' );
		remove_all_filters( 'bbai_audit_resolve_usage_context' );

		$this->discovery = new Image_Discovery_Service();
	}

	protected function tearDown(): void {
		remove_all_filters( 'bbai_audit_batch_size' );
		remove_all_filters( 'bbai_audit_resolve_usage_context' );
	}

	/**
	 * @param array<string,mixed> $overrides Row overrides.
	 * @return array<string,mixed>
	 */
	private function row( array $overrides = array() ): array {
		return array_merge(
			array(
				'ID'             => 42,
				'post_title'     => 'Heron',
				'post_excerpt'   => 'A caption',
				'post_content'   => 'A description',
				'post_parent'    => 0,
				'post_date'      => '2026-01-01 10:00:00',
				'post_mime_type' => 'image/jpeg',
				'alt_text'       => null,
				'decorative'     => null,
				'attached_file'  => '2026/01/grey-heron.jpg',
			),
			$overrides
		);
	}

	/* ─── Batch sizing ─── */

	public function test_batch_size_defaults_and_clamps(): void {
		$this->assertSame( Image_Discovery_Service::DEFAULT_BATCH_SIZE, Image_Discovery_Service::batch_size( null ) );
		$this->assertSame( Image_Discovery_Service::MIN_BATCH_SIZE, Image_Discovery_Service::batch_size( 1 ) );
		$this->assertSame( Image_Discovery_Service::MAX_BATCH_SIZE, Image_Discovery_Service::batch_size( 10000 ) );
		$this->assertSame( 50, Image_Discovery_Service::batch_size( 50 ) );
	}

	public function test_batch_size_is_filterable_but_still_clamped(): void {
		add_filter(
			'bbai_audit_batch_size',
			static function () {
				return 99999;
			}
		);

		$this->assertSame( Image_Discovery_Service::MAX_BATCH_SIZE, Image_Discovery_Service::batch_size( null ) );
	}

	/* ─── Row mapping ─── */

	public function test_a_null_alt_column_means_no_alt_meta_row(): void {
		$context = $this->discovery->build_context( $this->row( array( 'alt_text' => null ) ) );

		$this->assertFalse( $context->has_alt_meta() );
	}

	public function test_an_empty_alt_column_means_the_meta_row_exists_and_is_blank(): void {
		$context = $this->discovery->build_context( $this->row( array( 'alt_text' => '' ) ) );

		$this->assertTrue( $context->has_alt_meta() );
		$this->assertFalse( $context->has_alt_text() );
	}

	public function test_the_filename_stem_drops_the_directory_and_extension(): void {
		$context = $this->discovery->build_context( $this->row( array( 'attached_file' => '2026/01/grey-heron.jpg' ) ) );

		$this->assertSame( 'grey-heron', $context->get_filename() );
	}

	public function test_a_filename_without_an_extension_survives_intact(): void {
		$context = $this->discovery->build_context( $this->row( array( 'attached_file' => 'uploads/heron' ) ) );

		$this->assertSame( 'heron', $context->get_filename() );
	}

	public function test_a_missing_attached_file_yields_an_empty_stem(): void {
		$context = $this->discovery->build_context( $this->row( array( 'attached_file' => '' ) ) );

		$this->assertSame( '', $context->get_filename() );
	}

	public function test_the_decorative_flag_is_read_from_meta(): void {
		$this->assertTrue( $this->discovery->build_context( $this->row( array( 'decorative' => '1' ) ) )->is_decorative() );
		$this->assertFalse( $this->discovery->build_context( $this->row( array( 'decorative' => null ) ) )->is_decorative() );
		$this->assertFalse( $this->discovery->build_context( $this->row( array( 'decorative' => '0' ) ) )->is_decorative() );
	}

	public function test_titles_captions_and_descriptions_are_carried_through(): void {
		$context = $this->discovery->build_context( $this->row() );

		$this->assertSame( 'Heron', $context->get_title() );
		$this->assertSame( 'A caption', $context->get_caption() );
		$this->assertSame( 'A description', $context->get_description() );
		$this->assertSame( 'image/jpeg', $context->get_mime_type() );
		$this->assertSame( '2026-01-01 10:00:00', $context->get_uploaded_at() );
	}

	/* ─── Duplicate map lookup ─── */

	public function test_duplicate_group_size_comes_from_the_run_map(): void {
		$alt  = 'A grey heron standing in shallow water';
		$hash = sha1( \BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context::normalise( $alt ) );

		$context = $this->discovery->build_context(
			$this->row( array( 'alt_text' => $alt ) ),
			array(),
			array( $hash => 5 )
		);

		$this->assertSame( 5, $context->get_duplicate_group_size() );
	}

	public function test_a_value_absent_from_the_map_is_unique(): void {
		$context = $this->discovery->build_context(
			$this->row( array( 'alt_text' => 'Something else entirely' ) ),
			array(),
			array( sha1( 'unrelated' ) => 9 )
		);

		$this->assertSame( 0, $context->get_duplicate_group_size() );
	}

	public function test_duplicate_lookup_is_case_and_punctuation_insensitive(): void {
		$hash = sha1( \BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context::normalise( 'a grey heron' ) );

		$context = $this->discovery->build_context(
			$this->row( array( 'alt_text' => 'A  GREY, Heron!' ) ),
			array(),
			array( $hash => 3 )
		);

		$this->assertSame( 3, $context->get_duplicate_group_size() );
	}

	/* ─── Usage context ─── */

	public function test_an_attached_image_reports_its_parent(): void {
		$GLOBALS['bbai_test_post_titles'][7] = 'About Us';

		$context = $this->discovery->build_context(
			$this->row( array( 'post_parent' => 7 ) ),
			array(
				'known'    => true,
				'featured' => array(),
			)
		);

		$this->assertTrue( $context->has_usage() );
		$this->assertTrue( $context->is_usage_context_known() );
		$this->assertSame( 'Attached to: About Us', $context->get_context_summary() );
	}

	public function test_a_featured_image_counts_as_used(): void {
		$context = $this->discovery->build_context(
			$this->row( array( 'ID' => 42, 'post_parent' => 0 ) ),
			array(
				'known'    => true,
				'featured' => array( 42 => true ),
			)
		);

		$this->assertTrue( $context->has_usage() );
		$this->assertSame( 'Used as a featured image', $context->get_context_summary() );
	}

	public function test_an_unused_image_is_reported_as_unattached(): void {
		$context = $this->discovery->build_context(
			$this->row( array( 'post_parent' => 0 ) ),
			array(
				'known'    => true,
				'featured' => array(),
			)
		);

		$this->assertFalse( $context->has_usage() );
		$this->assertTrue( $context->is_usage_context_known() );
		$this->assertSame( 'Not attached to a post', $context->get_context_summary() );
	}

	public function test_unresolved_usage_is_reported_as_unknown_not_as_unused(): void {
		$context = $this->discovery->build_context(
			$this->row( array( 'post_parent' => 0 ) ),
			array(
				'known'    => false,
				'featured' => array(),
			)
		);

		$this->assertFalse( $context->is_usage_context_known() );
		$this->assertSame( 'Usage not determined', $context->get_context_summary() );
	}

	public function test_usage_resolution_can_be_switched_off(): void {
		add_filter(
			'bbai_audit_resolve_usage_context',
			static function () {
				return false;
			}
		);

		$usage = $this->discovery->resolve_usage( array( 1, 2, 3 ) );

		$this->assertFalse( $usage['known'] );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries, 'Disabling usage resolution must skip the query' );
	}

	public function test_a_failed_usage_lookup_does_not_claim_images_are_unused(): void {
		$GLOBALS['wpdb']->results_result = null;

		$usage = $this->discovery->resolve_usage( array( 1, 2 ) );

		$this->assertFalse( $usage['known'] );
	}

	public function test_usage_resolution_uses_one_query_for_the_whole_batch(): void {
		$GLOBALS['wpdb']->results_result = array( array( 'meta_value' => '2' ) );

		$usage = $this->discovery->resolve_usage( array( 1, 2, 3, 4, 5 ) );

		$this->assertCount( 1, $GLOBALS['wpdb']->queries );
		$this->assertSame( array( 2 => true ), $usage['featured'] );
		$this->assertStringContainsString( "IN (1, 2, 3, 4, 5)", $GLOBALS['wpdb']->last_query() );
	}

	public function test_an_empty_id_list_needs_no_query(): void {
		$usage = $this->discovery->resolve_usage( array() );

		$this->assertTrue( $usage['known'] );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	/* ─── Duplicate map construction ─── */

	public function test_the_duplicate_map_normalises_and_merges_groups(): void {
		$GLOBALS['wpdb']->results_result = array(
			array(
				'alt_text' => 'A grey heron in the water',
				'cnt'      => '3',
			),
			array(
				'alt_text' => 'a  GREY heron in the water!',
				'cnt'      => '2',
			),
		);

		$result = $this->discovery->build_duplicate_map();
		$hash   = sha1( \BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context::normalise( 'A grey heron in the water' ) );

		$this->assertSame( 5, $result['map'][ $hash ], 'Values differing only in case merge into one group' );
		$this->assertFalse( $result['truncated'] );
	}

	public function test_the_duplicate_map_skips_values_that_normalise_to_nothing(): void {
		$GLOBALS['wpdb']->results_result = array(
			array(
				'alt_text' => '--- !!! ---',
				'cnt'      => '4',
			),
		);

		$this->assertSame( array(), $this->discovery->build_duplicate_map()['map'] );
	}

	public function test_the_duplicate_map_is_built_with_one_bounded_query(): void {
		$GLOBALS['wpdb']->results_result = array();

		$this->discovery->build_duplicate_map();

		$this->assertCount( 1, $GLOBALS['wpdb']->queries );

		$query = $GLOBALS['wpdb']->last_query();
		$this->assertStringContainsString( 'HAVING cnt > 1', $query );
		$this->assertStringContainsString( 'LIMIT ' . Image_Discovery_Service::DUPLICATE_MAX_GROUPS, $query );
		$this->assertStringContainsString( 'CHAR_LENGTH(TRIM(alt.meta_value)) >= ' . Image_Discovery_Service::DUPLICATE_MIN_LENGTH, $query );
	}

	public function test_a_failed_duplicate_query_yields_an_empty_map(): void {
		$GLOBALS['wpdb']->results_result = null;

		$result = $this->discovery->build_duplicate_map();

		$this->assertSame( array(), $result['map'] );
		$this->assertFalse( $result['truncated'] );
	}

	/* ─── Batch query shape ─── */

	public function test_the_first_batch_starts_from_the_newest_image(): void {
		$GLOBALS['wpdb']->results_result = array();

		$this->discovery->get_batch_rows( 0, 100 );
		$query = $GLOBALS['wpdb']->last_query();

		$this->assertStringContainsString( 'ORDER BY p.ID DESC', $query );
		$this->assertStringContainsString( 'LIMIT 100', $query );
		$this->assertStringContainsString( '(0 = 0 OR p.ID < 0)', $query );
	}

	public function test_later_batches_use_keyset_pagination_rather_than_offset(): void {
		$GLOBALS['wpdb']->results_result = array();

		$this->discovery->get_batch_rows( 500, 100 );
		$query = $GLOBALS['wpdb']->last_query();

		$this->assertStringContainsString( '(500 = 0 OR p.ID < 500)', $query );
		$this->assertStringNotContainsString( 'OFFSET', $query );
	}

	public function test_only_image_attachments_are_in_scope(): void {
		$GLOBALS['wpdb']->var_result = 7;

		$this->assertSame( 7, $this->discovery->count_images() );

		$query = $GLOBALS['wpdb']->last_query();
		$this->assertStringContainsString( "post_type = 'attachment'", $query );
		$this->assertStringContainsString( "post_status = 'inherit'", $query );
		$this->assertStringContainsString( "post_mime_type LIKE 'image/%'", $query );
	}

	public function test_get_batch_resolves_usage_for_the_whole_batch_at_once(): void {
		$wpdb = $GLOBALS['wpdb'];

		$wpdb->results_queue = array(
			array( $this->row( array( 'ID' => 90 ) ), $this->row( array( 'ID' => 80 ) ) ),
			array(),
		);

		$contexts = $this->discovery->get_batch( 0, 100 );

		$this->assertCount( 2, $contexts );
		// One discovery query plus one usage query — never one per image.
		$this->assertCount( 2, $wpdb->queries );
	}
}
