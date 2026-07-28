<?php
/**
 * One-time, non-destructive migration from the earlier (shipped-then-reverted)
 * `wp_bbai_image_audit` table into the shared `wp_optiai_scan_items` table.
 *
 * That table was briefly live in production (merged via PR #26, then
 * reverted) so a small number of sites may still carry it with real user
 * review decisions (resolved / dismissed / marked decorative) worth
 * preserving. This class does not depend on the reverted branch's code at
 * runtime — it re-derives scores from the same documented formula that
 * shipped with it (worst-issue-severity deduction: critical=100, warning=40,
 * review=15, information=5; settled rows contribute 0), using a static copy
 * of the issue catalogue so the label/explanation carries over too.
 *
 * The legacy table itself is left untouched — this only reads from it once
 * and writes into the new shared table. Safe to call on every plugin load;
 * it no-ops once `optiai_alt_text_legacy_migrated` is set.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator\Scoring;

use OptiAI\Core\Scan\Scan_Repository;
use OptiAI\Core\Scan\Schema;
use OptiAI\Core\Scoring\Issue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Legacy_Audit_Migrator {

	const LEGACY_TABLE_SLUG = 'bbai_image_audit';
	const MIGRATED_OPTION   = 'optiai_alt_text_legacy_migrated';
	const SETTLED_OPTION    = 'optiai_alt_text_legacy_settled_ids';

	/**
	 * Static copy of the reverted branch's issue catalogue (code => [severity, label, explanation]).
	 * Only the fields this migration needs — see includes/audit/class-audit-issue.php in the
	 * pre-revert history for the authoritative, fuller version if this ever needs extending.
	 *
	 * @return array<string,array{severity:string,label:string,explanation:string}>
	 */
	private static function legacy_issue_catalogue() {
		return array(
			'missing_alt'               => array(
				'severity'    => Issue::SEVERITY_CRITICAL,
				'label'       => __( 'No alt text', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'This image has no alt text stored at all, so screen-reader users get nothing but the file name.', 'beepbeep-ai-alt-text-generator' ),
			),
			'empty_alt_review_required' => array(
				'severity'    => Issue::SEVERITY_REVIEW,
				'label'       => __( 'Empty alt text — review needed', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'The alt attribute is set but empty. Correct for decorative images, wrong for meaningful ones.', 'beepbeep-ai-alt-text-generator' ),
			),
			'filename_as_alt'           => array(
				'severity'    => Issue::SEVERITY_WARNING,
				'label'       => __( 'Alt text is the file name', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'The alt text repeats the file name, which usually describes the upload rather than the picture.', 'beepbeep-ai-alt-text-generator' ),
			),
			'generic_alt'               => array(
				'severity'    => Issue::SEVERITY_WARNING,
				'label'       => __( 'Generic alt text', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'The alt text only names the medium or uses placeholder words.', 'beepbeep-ai-alt-text-generator' ),
			),
			'duplicate_alt'             => array(
				'severity'    => Issue::SEVERITY_WARNING,
				'label'       => __( 'Alt text used on other images', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'Several images share this exact alt text.', 'beepbeep-ai-alt-text-generator' ),
			),
			'alt_too_short'             => array(
				'severity'    => Issue::SEVERITY_WARNING,
				'label'       => __( 'Alt text is very short', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'Too few meaningful words to convey what the image shows.', 'beepbeep-ai-alt-text-generator' ),
			),
			'alt_too_long'              => array(
				'severity'    => Issue::SEVERITY_INFORMATION,
				'label'       => __( 'Alt text is very long', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'Long alt text can be tiring to listen to.', 'beepbeep-ai-alt-text-generator' ),
			),
			'possible_keyword_stuffing' => array(
				'severity'    => Issue::SEVERITY_WARNING,
				'label'       => __( 'Possible keyword stuffing', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'This alt text repeats words or reads as a list of keywords.', 'beepbeep-ai-alt-text-generator' ),
			),
			'orphaned_attachment'       => array(
				'severity'    => Issue::SEVERITY_INFORMATION,
				'label'       => __( 'Not attached to any post', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'No post or page appears to link to this image.', 'beepbeep-ai-alt-text-generator' ),
			),
			'no_usage_context'         => array(
				'severity'    => Issue::SEVERITY_INFORMATION,
				'label'       => __( 'Usage not determined', 'beepbeep-ai-alt-text-generator' ),
				'explanation' => __( 'Where this image is used could not be determined by the previous audit.', 'beepbeep-ai-alt-text-generator' ),
			),
		);
	}

	/** Severity -> deduction, matching the pre-revert Image_Accessibility_Score_Service formula. */
	private static function severity_weight( $severity ) {
		switch ( $severity ) {
			case Issue::SEVERITY_CRITICAL:
				return 100;
			case Issue::SEVERITY_WARNING:
				return 40;
			case Issue::SEVERITY_REVIEW:
				return 15;
			case Issue::SEVERITY_INFORMATION:
				return 5;
			default:
				return 0;
		}
	}

	/**
	 * Run the migration once. Safe to call on every request; no-ops after
	 * the first successful pass (or immediately if the legacy table never
	 * existed on this site).
	 *
	 * @return void
	 */
	public static function maybe_run() {
		if ( get_option( self::MIGRATED_OPTION ) ) {
			return;
		}

		if ( ! self::legacy_table_exists() ) {
			// Nothing to migrate — mark done so we don't check on every load.
			update_option( self::MIGRATED_OPTION, 'no-legacy-table' );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::LEGACY_TABLE_SLUG;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table name is prefix-built, no user input.
		$rows = $wpdb->get_results( "SELECT attachment_id, issue_codes, audit_status FROM {$table}", ARRAY_A );
		if ( empty( $rows ) ) {
			update_option( self::MIGRATED_OPTION, 'empty' );
			return;
		}

		$repository   = new Scan_Repository( Alt_Text_Scan_Service::MODULE );
		$catalogue    = self::legacy_issue_catalogue();
		$settled_ids  = array();
		$migrated     = 0;

		foreach ( $rows as $row ) {
			$attachment_id = (int) $row['attachment_id'];
			$post          = get_post( $attachment_id );
			if ( ! $post ) {
				continue; // Attachment no longer exists — nothing to carry over.
			}

			$status = (string) $row['audit_status'];
			$alt    = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

			if ( in_array( $status, array( 'resolved', 'dismissed', 'decorative' ), true ) ) {
				// Settled — the administrator already made a call on this
				// image. Preserve that decision instead of re-flagging it,
				// and record the id so a future decorative-image feature can
				// pick it back up.
				$settled_ids[ $status ][] = $attachment_id;
				$repository->upsert_item( (string) $attachment_id, 'image', 100, 'excellent', array(), $alt );
				++$migrated;
				continue;
			}

			$codes  = array_filter( array_map( 'trim', explode( ',', trim( (string) $row['issue_codes'], ',' ) ) ) );
			$issues = array();
			$worst_weight = 0;

			foreach ( $codes as $code ) {
				if ( ! isset( $catalogue[ $code ] ) ) {
					continue;
				}
				$def       = $catalogue[ $code ];
				$weight    = self::severity_weight( $def['severity'] );
				$worst_weight = max( $worst_weight, $weight );
				$issues[]  = new Issue( $code, $def['severity'], $weight, $def['explanation'] );
			}

			$score  = max( 0, 100 - $worst_weight );
			$status_band = \OptiAI\Core\Scoring\Score_Service::status_for_score( $score );

			$repository->upsert_item( (string) $attachment_id, 'image', $score, $status_band, $issues, $alt );
			++$migrated;
		}

		if ( ! empty( $settled_ids ) ) {
			update_option( self::SETTLED_OPTION, $settled_ids );
		}

		update_option( self::MIGRATED_OPTION, array(
			'migrated_at'    => current_time( 'mysql' ),
			'rows_migrated'  => $migrated,
		) );
	}

	private static function legacy_table_exists() {
		global $wpdb;
		$table = $wpdb->prefix . self::LEGACY_TABLE_SLUG;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table name only, no user input.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}
}
