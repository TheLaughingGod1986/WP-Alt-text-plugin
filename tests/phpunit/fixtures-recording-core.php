<?php
/**
 * Core subclass that records generate_and_save() calls instead of running
 * them. Loaded only inside separate-process queue tests, after the stubs.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\Core;

if ( ! class_exists( 'BBAI_Test_Recording_Core', false ) ) {
	class BBAI_Test_Recording_Core extends Core {
		/** @var int[] */
		public $generated = array();

		public function generate_and_save( $attachment_id, $source = 'manual', int $retry_count = 0, array $feedback = array(), $regenerate = false, bool $skip_trial_gate = false ) {
			$this->generated[] = (int) $attachment_id;
			update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', 'Regenerated alt' );
			return 'Regenerated alt';
		}
	}
}
