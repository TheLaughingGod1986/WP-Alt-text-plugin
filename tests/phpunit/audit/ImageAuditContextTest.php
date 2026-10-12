<?php
/**
 * Text normalisation and derived metrics on the audit context.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;
use PHPUnit\Framework\TestCase;

final class ImageAuditContextTest extends TestCase {

	/**
	 * @param array<string,mixed> $overrides Field overrides.
	 */
	private function context( array $overrides = array() ): Image_Audit_Context {
		return new Image_Audit_Context( array_merge( array( 'attachment_id' => 1 ), $overrides ) );
	}

	public function test_normalisation_flattens_case_punctuation_and_whitespace(): void {
		$this->assertSame( 'beach sunset', Image_Audit_Context::normalise( 'Beach-Sunset' ) );
		$this->assertSame( 'beach sunset', Image_Audit_Context::normalise( '  BEACH   sunset  ' ) );
		$this->assertSame( 'beach sunset', Image_Audit_Context::normalise( 'beach_sunset' ) );
		$this->assertSame( 'beach sunset', Image_Audit_Context::normalise( 'Beach, Sunset!' ) );
		$this->assertSame( '', Image_Audit_Context::normalise( '   ' ) );
		$this->assertSame( '', Image_Audit_Context::normalise( '---' ) );
		$this->assertSame( '', Image_Audit_Context::normalise( '' ) );
	}

	public function test_values_that_differ_only_in_case_or_spacing_normalise_alike(): void {
		$this->assertSame(
			Image_Audit_Context::normalise( 'A Dog On The Beach' ),
			Image_Audit_Context::normalise( 'a  dog on the   beach' )
		);
	}

	public function test_null_alt_and_empty_alt_are_distinguishable(): void {
		$no_meta = $this->context( array( 'alt' => null ) );
		$empty   = $this->context( array( 'alt' => '' ) );

		$this->assertFalse( $no_meta->has_alt_meta() );
		$this->assertFalse( $no_meta->has_alt_text() );

		$this->assertTrue( $empty->has_alt_meta() );
		$this->assertFalse( $empty->has_alt_text() );
	}

	public function test_a_missing_alt_key_is_treated_as_no_meta_row(): void {
		$this->assertFalse( $this->context()->has_alt_meta() );
	}

	public function test_whitespace_only_alt_counts_as_having_no_text(): void {
		$context = $this->context( array( 'alt' => "  \n\t " ) );

		$this->assertTrue( $context->has_alt_meta() );
		$this->assertFalse( $context->has_alt_text() );
		$this->assertSame( 0, $context->get_alt_length() );
	}

	public function test_alt_length_ignores_surrounding_whitespace(): void {
		$this->assertSame( 5, $this->context( array( 'alt' => '  hello  ' ) )->get_alt_length() );
	}

	public function test_meaningful_tokens_exclude_stop_words_and_generic_terms(): void {
		$context = $this->context( array( 'alt' => 'a photo of the heron' ) );

		$this->assertSame( array( 'heron' ), $context->get_meaningful_tokens() );
	}

	public function test_meaningful_tokens_drop_single_characters(): void {
		$context = $this->context( array( 'alt' => 'a b heron' ) );

		$this->assertSame( array( 'heron' ), $context->get_meaningful_tokens() );
	}

	public function test_redundant_prefixes_are_stripped(): void {
		// The article belongs to the subject, so it survives the prefix removal.
		$this->assertSame(
			'a grey heron',
			$this->context( array( 'alt' => 'A photo of a grey heron' ) )->get_alt_without_redundant_prefix()
		);

		$this->assertSame(
			'grey heron',
			$this->context( array( 'alt' => 'Image of grey heron' ) )->get_alt_without_redundant_prefix()
		);
	}

	public function test_a_bare_prefix_leaves_nothing_behind(): void {
		$this->assertSame(
			'',
			$this->context( array( 'alt' => 'image of' ) )->get_alt_without_redundant_prefix()
		);
	}

	public function test_text_without_a_prefix_is_untouched(): void {
		$this->assertSame(
			'grey heron at dawn',
			$this->context( array( 'alt' => 'Grey heron at dawn' ) )->get_alt_without_redundant_prefix()
		);
	}

	public function test_alt_hash_is_stable_across_cosmetic_differences(): void {
		$first  = $this->context( array( 'alt' => 'A Dog On The Beach' ) )->get_alt_hash();
		$second = $this->context( array( 'alt' => 'a dog on the  beach' ) )->get_alt_hash();

		$this->assertSame( $first, $second );
		$this->assertSame( 40, strlen( $first ) );
	}

	public function test_empty_alt_hashes_to_an_empty_string(): void {
		$this->assertSame( '', $this->context( array( 'alt' => null ) )->get_alt_hash() );
		$this->assertSame( '', $this->context( array( 'alt' => '   ' ) )->get_alt_hash() );
	}

	public function test_different_alt_text_produces_different_hashes(): void {
		$this->assertNotSame(
			$this->context( array( 'alt' => 'A grey heron' ) )->get_alt_hash(),
			$this->context( array( 'alt' => 'A white egret' ) )->get_alt_hash()
		);
	}

	public function test_tokens_are_empty_when_there_is_no_alt_text(): void {
		$context = $this->context( array( 'alt' => null ) );

		$this->assertSame( array(), $context->get_alt_tokens() );
		$this->assertSame( array(), $context->get_meaningful_tokens() );
	}

	public function test_numeric_fields_are_coerced_and_floored_at_zero(): void {
		$context = $this->context(
			array(
				'parent_post_id'       => '-4',
				'duplicate_group_size' => '-1',
				'attachment_id'        => '42',
			)
		);

		$this->assertSame( 0, $context->get_parent_post_id() );
		$this->assertSame( 0, $context->get_duplicate_group_size() );
		$this->assertSame( 42, $context->get_attachment_id() );
	}
}
