<?php
/**
 * Deterministic behaviour of the individual audit rules.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Alt_Too_Long_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Alt_Too_Short_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Duplicate_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Empty_Alt_Review_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Filename_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Generic_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Keyword_Stuffing_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Missing_Alt_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\No_Usage_Context_Rule;
use BeepBeepAI\AltTextGenerator\Audit\Rules\Orphaned_Attachment_Rule;
use PHPUnit\Framework\TestCase;

final class AuditRulesTest extends TestCase {

	/**
	 * Build a context, defaulting the fields a given test does not care about.
	 *
	 * @param array<string,mixed> $overrides Field overrides.
	 */
	private function context( array $overrides = array() ): Image_Audit_Context {
		return new Image_Audit_Context(
			array_merge(
				array(
					'attachment_id'       => 101,
					'alt'                 => null,
					'filename'            => 'beach-sunset',
					'usage_context_known' => true,
					'has_usage'           => true,
					'parent_post_id'      => 7,
				),
				$overrides
			)
		);
	}

	/**
	 * Run a rule the way the registry does: gate on applies_to first.
	 */
	private function fires( $rule, Image_Audit_Context $context ): bool {
		return $rule->applies_to( $context ) && $rule->evaluate( $context );
	}

	/* ─── missing_alt vs empty_alt_review_required ─── */

	public function test_missing_alt_fires_when_no_alt_meta_row_exists(): void {
		$rule = new Missing_Alt_Rule();

		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => null ) ) ) );
	}

	public function test_missing_alt_does_not_fire_for_an_empty_alt_attribute(): void {
		$rule = new Missing_Alt_Rule();

		// An empty alt attribute is a deliberate marking, not an absent one.
		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => '' ) ) ) );
	}

	public function test_empty_alt_review_fires_only_when_the_meta_row_exists_and_is_blank(): void {
		$rule = new Empty_Alt_Review_Rule();

		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => '' ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => '   ' ) ) ) );
		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => null ) ) ) );
		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => 'A dog on a beach' ) ) ) );
	}

	public function test_decorative_images_are_excluded_from_both_alt_absence_rules(): void {
		$missing = new Missing_Alt_Rule();
		$empty   = new Empty_Alt_Review_Rule();

		$this->assertFalse(
			$this->fires( $missing, $this->context( array( 'alt' => null, 'is_decorative' => true ) ) )
		);
		$this->assertFalse(
			$this->fires( $empty, $this->context( array( 'alt' => '', 'is_decorative' => true ) ) )
		);
	}

	/* ─── filename_as_alt ─── */

	public function test_filename_alt_fires_when_alt_matches_the_file_name(): void {
		$rule = new Filename_Alt_Rule();

		$this->assertTrue(
			$this->fires( $rule, $this->context( array( 'alt' => 'beach-sunset', 'filename' => 'beach-sunset' ) ) )
		);

		// Normalisation must see through separators and casing.
		$this->assertTrue(
			$this->fires( $rule, $this->context( array( 'alt' => 'Beach_Sunset', 'filename' => 'beach-sunset' ) ) )
		);
	}

	public function test_filename_alt_fires_on_file_extensions_and_camera_patterns(): void {
		$rule = new Filename_Alt_Rule();

		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'holiday.jpg' ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'IMG_4821' ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'DSC-0042' ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'screenshot-2024-05-01' ) ) ) );
	}

	public function test_filename_alt_leaves_real_descriptions_alone(): void {
		$rule = new Filename_Alt_Rule();

		$this->assertFalse(
			$this->fires(
				$rule,
				$this->context(
					array(
						'alt'      => 'Sun setting over a quiet beach',
						'filename' => 'beach-sunset',
					)
				)
			)
		);
	}

	/* ─── generic_alt ─── */

	public function test_generic_alt_fires_on_medium_words_and_placeholders(): void {
		$rule = new Generic_Alt_Rule();

		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'image' ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'a photo of an image' ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'placeholder' ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'photo 1234' ) ) ) );
	}

	public function test_generic_alt_passes_when_one_real_word_survives(): void {
		$rule = new Generic_Alt_Rule();

		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => 'logo of Acme Ltd' ) ) ) );
		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => 'a photo of a heron' ) ) ) );
	}

	/* ─── duplicate_alt ─── */

	public function test_duplicate_alt_fires_for_substantial_shared_values(): void {
		$rule = new Duplicate_Alt_Rule();

		$this->assertTrue(
			$this->fires(
				$rule,
				$this->context(
					array(
						'alt'                  => 'Team photo taken at the summer conference',
						'duplicate_group_size' => 4,
					)
				)
			)
		);
	}

	public function test_duplicate_alt_ignores_unique_short_and_thin_values(): void {
		$rule = new Duplicate_Alt_Rule();

		// Unique.
		$this->assertFalse(
			$this->fires(
				$rule,
				$this->context(
					array(
						'alt'                  => 'Team photo taken at the summer conference',
						'duplicate_group_size' => 1,
					)
				)
			)
		);

		// Shared but too short to be worth reporting.
		$this->assertFalse(
			$this->fires( $rule, $this->context( array( 'alt' => 'Acme logo', 'duplicate_group_size' => 30 ) ) )
		);

		// Long enough, but not enough meaningful words.
		$this->assertFalse(
			$this->fires( $rule, $this->context( array( 'alt' => 'the a an of the', 'duplicate_group_size' => 5 ) ) )
		);
	}

	public function test_duplicate_alt_skips_decorative_images(): void {
		$rule = new Duplicate_Alt_Rule();

		$this->assertFalse(
			$this->fires(
				$rule,
				$this->context(
					array(
						'alt'                  => 'Decorative divider used across the site',
						'duplicate_group_size' => 12,
						'is_decorative'        => true,
					)
				)
			)
		);
	}

	/* ─── length thresholds ─── */

	public function test_alt_too_short_boundaries(): void {
		$rule = new Alt_Too_Short_Rule();

		// 11 characters — under the 12-character floor.
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'red barn do' ) ) ) );

		// Exactly 12 characters with three meaningful words — passes both floors.
		$twelve = 'red barn dog';
		$this->assertSame( 12, strlen( $twelve ) );
		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => $twelve ) ) ) );

		// Long enough, but only two meaningful words.
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => 'a photograph of the barn' ) ) ) );
	}

	public function test_alt_too_long_boundary_is_250_characters(): void {
		$rule = new Alt_Too_Long_Rule();

		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => str_repeat( 'a', 250 ) ) ) ) );
		$this->assertTrue( $this->fires( $rule, $this->context( array( 'alt' => str_repeat( 'a', 251 ) ) ) ) );
	}

	public function test_length_rules_do_not_fire_without_alt_text(): void {
		$short = new Alt_Too_Short_Rule();
		$long  = new Alt_Too_Long_Rule();

		$this->assertFalse( $this->fires( $short, $this->context( array( 'alt' => null ) ) ) );
		$this->assertFalse( $this->fires( $short, $this->context( array( 'alt' => '' ) ) ) );
		$this->assertFalse( $this->fires( $long, $this->context( array( 'alt' => null ) ) ) );
	}

	/* ─── possible_keyword_stuffing ─── */

	public function test_keyword_stuffing_fires_on_repetition_and_keyword_lists(): void {
		$rule = new Keyword_Stuffing_Rule();

		$this->assertTrue(
			$this->fires( $rule, $this->context( array( 'alt' => 'shoes running shoes cheap shoes buy shoes' ) ) )
		);

		$this->assertTrue(
			$this->fires( $rule, $this->context( array( 'alt' => 'running shoes, trainers, sneakers, cheap footwear' ) ) )
		);
	}

	public function test_keyword_stuffing_leaves_ordinary_sentences_alone(): void {
		$rule = new Keyword_Stuffing_Rule();

		$this->assertFalse(
			$this->fires(
				$rule,
				$this->context( array( 'alt' => 'A runner lacing up bright orange trainers on a wooden bench' ) )
			)
		);

		// Repeated stop words are prose, not stuffing.
		$this->assertFalse(
			$this->fires( $rule, $this->context( array( 'alt' => 'a man and a woman and a dog walking' ) ) )
		);

		// Short text never qualifies, however repetitive.
		$this->assertFalse( $this->fires( $rule, $this->context( array( 'alt' => 'shoes shoes shoes' ) ) ) );
	}

	/* ─── usage context ─── */

	public function test_orphaned_attachment_requires_known_usage(): void {
		$rule = new Orphaned_Attachment_Rule();

		$this->assertTrue(
			$this->fires(
				$rule,
				$this->context(
					array(
						'parent_post_id'      => 0,
						'has_usage'           => false,
						'usage_context_known' => true,
					)
				)
			)
		);

		// Usage was never established — that is No_Usage_Context_Rule's finding, not an orphan claim.
		$this->assertFalse(
			$this->fires(
				$rule,
				$this->context(
					array(
						'parent_post_id'      => 0,
						'has_usage'           => false,
						'usage_context_known' => false,
					)
				)
			)
		);
	}

	public function test_no_usage_context_fires_only_when_usage_is_unknown(): void {
		$rule = new No_Usage_Context_Rule();

		$this->assertTrue( $this->fires( $rule, $this->context( array( 'usage_context_known' => false ) ) ) );
		$this->assertFalse( $this->fires( $rule, $this->context( array( 'usage_context_known' => true ) ) ) );
	}

	/* ─── rule metadata ─── */

	public function test_every_rule_reports_metadata_matching_the_catalogue(): void {
		$rules = array(
			new Missing_Alt_Rule(),
			new Empty_Alt_Review_Rule(),
			new Filename_Alt_Rule(),
			new Generic_Alt_Rule(),
			new Duplicate_Alt_Rule(),
			new Alt_Too_Short_Rule(),
			new Alt_Too_Long_Rule(),
			new Keyword_Stuffing_Rule(),
			new Orphaned_Attachment_Rule(),
			new No_Usage_Context_Rule(),
		);

		foreach ( $rules as $rule ) {
			$definition = Audit_Issue::definition( $rule->get_code() );

			$this->assertIsArray( $definition, $rule->get_code() . ' has no catalogue entry' );
			$this->assertSame( $definition['severity'], $rule->get_severity() );
			$this->assertSame( (bool) $definition['auto_fixable'], $rule->is_auto_fixable() );
			$this->assertSame( (bool) $definition['requires_review'], $rule->requires_human_review() );
		}
	}
}
