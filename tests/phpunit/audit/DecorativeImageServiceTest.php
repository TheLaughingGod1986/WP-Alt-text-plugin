<?php
/**
 * Decorative decisions: recording, reversing, and their effect on the audit.
 */

declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;
use BeepBeepAI\AltTextGenerator\Audit\Audit_Rule_Registry;
use BeepBeepAI\AltTextGenerator\Audit\Decorative_Image_Service;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Context;
use BeepBeepAI\AltTextGenerator\Audit\Image_Audit_Repository;
use PHPUnit\Framework\TestCase;

final class DecorativeImageServiceTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['bbai_test_post_meta'] = array();
		$GLOBALS['bbai_test_actions']   = array();
	}

	/* ─── Recording the decision ─── */

	public function test_an_image_is_not_decorative_until_someone_says_so(): void {
		$this->assertFalse( Decorative_Image_Service::is_decorative( 42 ) );
	}

	public function test_marking_records_who_decided_and_when(): void {
		Decorative_Image_Service::mark( 42, 7 );

		$decision = Decorative_Image_Service::get_decision( 42 );

		$this->assertTrue( $decision['decorative'] );
		$this->assertSame( 7, $decision['set_by'] );
		$this->assertNotSame( '', $decision['set_at'] );
	}

	public function test_a_decision_can_be_reversed(): void {
		Decorative_Image_Service::mark( 42, 7 );
		$this->assertTrue( Decorative_Image_Service::is_decorative( 42 ) );

		Decorative_Image_Service::unmark( 42 );

		$decision = Decorative_Image_Service::get_decision( 42 );
		$this->assertFalse( $decision['decorative'] );
		$this->assertSame( '', $decision['set_at'], 'reversing clears the audit trail too' );
		$this->assertSame( 0, $decision['set_by'] );
	}

	public function test_set_handles_both_directions(): void {
		Decorative_Image_Service::set( 42, true, 1 );
		$this->assertTrue( Decorative_Image_Service::is_decorative( 42 ) );

		Decorative_Image_Service::set( 42, false, 1 );
		$this->assertFalse( Decorative_Image_Service::is_decorative( 42 ) );
	}

	public function test_invalid_ids_are_rejected(): void {
		$this->assertFalse( Decorative_Image_Service::mark( 0, 1 ) );
		$this->assertFalse( Decorative_Image_Service::unmark( -5 ) );
		$this->assertFalse( Decorative_Image_Service::is_decorative( 0 ) );
	}

	public function test_marking_fires_an_action_others_can_hook(): void {
		$fired = array();

		add_action(
			'bbai_audit_image_marked_decorative',
			static function ( $id, $user ) use ( &$fired ) {
				$fired[] = array( $id, $user );
			}
		);

		Decorative_Image_Service::mark( 42, 7 );

		$this->assertSame( array( array( 42, 7 ) ), $fired );
	}

	public function test_reversing_fires_its_own_action(): void {
		$fired = array();

		add_action(
			'bbai_audit_image_decorative_reversed',
			static function ( $id ) use ( &$fired ) {
				$fired[] = $id;
			}
		);

		Decorative_Image_Service::unmark( 42 );

		$this->assertSame( array( 42 ), $fired );
	}

	public function test_marking_does_not_touch_the_images_alt_text(): void {
		$GLOBALS['bbai_test_post_meta'][42]['_wp_attachment_image_alt'] = 'An existing description';

		Decorative_Image_Service::mark( 42, 1 );

		// Automatically emptying alt text would be an unrequested overwrite of the
		// user's own content, which this phase does not do.
		$this->assertSame(
			'An existing description',
			$GLOBALS['bbai_test_post_meta'][42]['_wp_attachment_image_alt']
		);
	}

	/* ─── Effect on the audit ─── */

	public function test_a_decorative_image_reports_no_alt_findings(): void {
		$registry = new Audit_Rule_Registry();

		$context = new Image_Audit_Context(
			array(
				'attachment_id'       => 42,
				'alt'                 => '',
				'is_decorative'       => true,
				'usage_context_known' => true,
				'has_usage'           => true,
				'parent_post_id'      => 3,
			)
		);

		$result = $registry->evaluate( $context );

		$this->assertNotContains( Audit_Issue::MISSING_ALT, $result['codes'] );
		$this->assertNotContains( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED, $result['codes'] );
	}

	public function test_the_same_image_reports_findings_once_the_decision_is_reversed(): void {
		$registry = new Audit_Rule_Registry();

		$context = new Image_Audit_Context(
			array(
				'attachment_id'       => 42,
				'alt'                 => '',
				'is_decorative'       => false,
				'usage_context_known' => true,
				'has_usage'           => true,
				'parent_post_id'      => 3,
			)
		);

		$this->assertContains( Audit_Issue::EMPTY_ALT_REVIEW_REQUIRED, $registry->evaluate( $context )['codes'] );
	}

	public function test_a_decorative_row_is_stored_as_settled_and_scores_nothing(): void {
		$context = new Image_Audit_Context(
			array(
				'attachment_id' => 42,
				'alt'           => '',
				'is_decorative' => true,
			)
		);

		$record = Image_Audit_Repository::build_record(
			$context,
			array(
				'codes'    => array(),
				'severity' => Audit_Issue::SEVERITY_INFORMATION,
			),
			'run-1'
		);

		$this->assertSame( Image_Audit_Repository::STATUS_DECORATIVE, $record['audit_status'] );
		$this->assertSame( 0.0, $record['score_contribution'] );
	}

	public function test_nothing_classifies_an_image_as_decorative_automatically(): void {
		$registry = new Audit_Rule_Registry();

		// A file name that screams "decoration" must still not decide for the user.
		$context = new Image_Audit_Context(
			array(
				'attachment_id'       => 42,
				'alt'                 => null,
				'filename'            => 'decorative-spacer-divider-ornament',
				'is_decorative'       => false,
				'usage_context_known' => true,
				'has_usage'           => true,
				'parent_post_id'      => 3,
			)
		);

		$this->assertContains(
			Audit_Issue::MISSING_ALT,
			$registry->evaluate( $context )['codes'],
			'a filename must never imply a decorative decision'
		);
	}
}
