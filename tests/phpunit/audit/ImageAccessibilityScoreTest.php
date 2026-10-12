<?php
/**
 * The health score formula, including its boundaries.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Image_Accessibility_Score_Service;
use PHPUnit\Framework\TestCase;

final class ImageAccessibilityScoreTest extends TestCase {

	protected function setUp(): void {
		remove_all_filters( 'bbai_audit_score' );
		remove_all_filters( 'bbai_audit_score_weights' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'bbai_audit_score' );
		remove_all_filters( 'bbai_audit_score_weights' );
	}

	/**
	 * @param array<string,int> $counters Counter overrides.
	 * @return array<string,mixed>
	 */
	private function score( array $counters ): array {
		return Image_Accessibility_Score_Service::calculate(
			array_merge(
				array(
					'total_images'   => 100,
					'scanned_images' => 100,
					'critical'       => 0,
					'warning'        => 0,
					'review'         => 0,
					'information'    => 0,
					'settled'        => 0,
				),
				$counters
			)
		);
	}

	public function test_a_clean_site_scores_100(): void {
		$this->assertSame( 100, $this->score( array() )['score'] );
	}

	public function test_a_site_where_every_image_is_missing_alt_scores_zero(): void {
		$this->assertSame( 0, $this->score( array( 'critical' => 100 ) )['score'] );
	}

	public function test_the_formula_is_the_documented_arithmetic(): void {
		// A quarter critical, the rest clean: 100 - (25 * 100 / 100) = 75.
		$this->assertSame( 75, $this->score( array( 'critical' => 25 ) )['score'] );

		// Half warnings: 100 - (50 * 40 / 100) = 80.
		$this->assertSame( 80, $this->score( array( 'warning' => 50 ) )['score'] );

		// All review: 100 - (100 * 15 / 100) = 85.
		$this->assertSame( 85, $this->score( array( 'review' => 100 ) )['score'] );

		// All informational: 100 - (100 * 5 / 100) = 95.
		$this->assertSame( 95, $this->score( array( 'information' => 100 ) )['score'] );
	}

	public function test_the_score_is_clamped_to_the_zero_hundred_range(): void {
		// More deductions than images cannot push below zero.
		$result = $this->score(
			array(
				'scanned_images' => 10,
				'critical'       => 10,
				'warning'        => 10,
			)
		);

		$this->assertSame( 0, $result['score'] );
	}

	public function test_settled_images_carry_no_deduction(): void {
		$result = $this->score( array( 'settled' => 100 ) );

		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 100, $result['settled_count'] );
	}

	public function test_an_unscanned_site_reports_a_full_score_and_zero_completion(): void {
		$result = $this->score(
			array(
				'total_images'   => 500,
				'scanned_images' => 0,
			)
		);

		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 0, $result['scan_completion_percent'] );
	}

	public function test_an_empty_library_does_not_divide_by_zero(): void {
		$result = $this->score(
			array(
				'total_images'   => 0,
				'scanned_images' => 0,
			)
		);

		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 0, $result['total_images'] );
		$this->assertSame( 0, $result['scan_completion_percent'] );
	}

	public function test_headline_counts_are_returned(): void {
		$result = $this->score(
			array(
				'critical'    => 10,
				'warning'     => 20,
				'review'      => 5,
				'information' => 5,
			)
		);

		$this->assertSame( 100, $result['total_images'] );
		$this->assertSame( 100, $result['scanned_images'] );
		$this->assertSame( 40, $result['images_with_issues'] );
		$this->assertSame( 60, $result['images_passing'] );
		$this->assertSame( 10, $result['critical_count'] );
		$this->assertSame( 20, $result['warning_count'] );
		$this->assertSame( 5, $result['review_count'] );
		$this->assertSame( 5, $result['information_count'] );
		$this->assertSame( 100, $result['scan_completion_percent'] );
	}

	public function test_partial_scan_reports_completion_percentage(): void {
		$result = $this->score(
			array(
				'total_images'   => 1000,
				'scanned_images' => 250,
				'critical'       => 50,
			)
		);

		$this->assertSame( 25, $result['scan_completion_percent'] );
		// Score reflects what has been scanned: 100 - (50 * 100 / 250) = 80.
		$this->assertSame( 80, $result['score'] );
	}

	public function test_negative_and_oversized_inputs_are_normalised(): void {
		$result = $this->score(
			array(
				'total_images'   => 10,
				'scanned_images' => 999,
				'critical'       => -5,
			)
		);

		$this->assertSame( 10, $result['scanned_images'] );
		$this->assertSame( 0, $result['critical_count'] );
		$this->assertSame( 100, $result['scan_completion_percent'] );
	}

	public function test_weights_are_replaceable_and_clamped(): void {
		add_filter(
			'bbai_audit_score_weights',
			static function ( $weights ) {
				$weights[ Audit_Issue::SEVERITY_WARNING ]  = 10;
				$weights[ Audit_Issue::SEVERITY_CRITICAL ] = 500; // Out of range.
				return $weights;
			}
		);

		$weights = Image_Accessibility_Score_Service::get_weights();

		$this->assertSame( 10, $weights[ Audit_Issue::SEVERITY_WARNING ] );
		$this->assertSame( 100, $weights[ Audit_Issue::SEVERITY_CRITICAL ] );

		// 100 - (50 * 10 / 100) = 95.
		$this->assertSame( 95, $this->score( array( 'warning' => 50 ) )['score'] );
	}

	public function test_the_whole_score_payload_is_replaceable(): void {
		add_filter(
			'bbai_audit_score',
			static function ( $result ) {
				$result['score'] = 42;
				return $result;
			}
		);

		$this->assertSame( 42, $this->score( array( 'critical' => 100 ) )['score'] );
	}

	public function test_score_bands(): void {
		$this->assertSame( '90-100', Image_Accessibility_Score_Service::band( 100 ) );
		$this->assertSame( '90-100', Image_Accessibility_Score_Service::band( 90 ) );
		$this->assertSame( '75-89', Image_Accessibility_Score_Service::band( 89 ) );
		$this->assertSame( '75-89', Image_Accessibility_Score_Service::band( 75 ) );
		$this->assertSame( '50-74', Image_Accessibility_Score_Service::band( 74 ) );
		$this->assertSame( '25-49', Image_Accessibility_Score_Service::band( 49 ) );
		$this->assertSame( '0-24', Image_Accessibility_Score_Service::band( 24 ) );
		$this->assertSame( '0-24', Image_Accessibility_Score_Service::band( -10 ) );
		$this->assertSame( '90-100', Image_Accessibility_Score_Service::band( 1000 ) );
	}

	public function test_the_disclaimer_is_present_and_makes_no_certification_claim(): void {
		$disclaimer = strtolower( Image_Accessibility_Score_Service::disclaimer() );

		$this->assertStringContainsString( 'opptiai health indicator', $disclaimer );
		$this->assertStringContainsString( 'not a formal accessibility certification', $disclaimer );
	}

	public function test_the_formula_version_is_reported(): void {
		$this->assertSame(
			Image_Accessibility_Score_Service::FORMULA_VERSION,
			$this->score( array() )['formula_version']
		);
	}
}
