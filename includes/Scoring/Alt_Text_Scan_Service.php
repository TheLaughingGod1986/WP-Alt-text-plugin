<?php
/**
 * Free, local, no-AI health scan of every image's alt text.
 *
 * Reuses the plugin's existing BBAI_Alt_Quality_Scorer (a mature,
 * already-shipped 0-100 scorer with hard-fail gates for missing, placeholder,
 * filename, generic, gibberish, and keyword-stuffed alt text) rather than
 * re-deriving equivalent rules from scratch, and adds the one check that
 * scorer does not attempt on its own: alt text repeated identically across
 * multiple unrelated images. Results are stored in the shared
 * wp_optiai_scan_items table (module = "alt_text") so the health dashboard,
 * Priority Action Centre, and Advanced Library all read from one place.
 *
 * @package BeepBeep_AI
 */

namespace BeepBeepAI\AltTextGenerator\Scoring;

use OptiAI\Core\Scan\Scan_Repository;
use OptiAI\Core\Scoring\Issue;
use OptiAI\Core\Scoring\Score_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alt_Text_Scan_Service {

	const MODULE = 'alt_text';

	/**
	 * Best-effort mapping from the scorer's fixed, translatable issue
	 * strings to stable machine codes the dashboard groups issues by.
	 * Matched by substring so the one dynamic (sprintf'd) message still
	 * resolves. Order matters: first match wins.
	 *
	 * @return array<string,string>
	 */
	private static function issue_code_map() {
		return array(
			'ALT text is missing'                          => 'missing_alt_text',
			'looks like a filename'                        => 'filename_alt_text',
			'identical to the filename'                     => 'filename_alt_text',
			'placeholder content'                          => 'placeholder_alt_text',
			'placeholder or non-descriptive'               => 'placeholder_alt_text',
			'only generic or filler words'                 => 'generic_alt_text',
			'generic filler words like "image"'            => 'generic_alt_text',
			'single word'                                  => 'alt_too_short',
			'lacks enough meaningful words'                => 'alt_too_short',
			'Too short for a screen reader'                => 'alt_too_short',
			'gibberish or nonsensical'                      => 'gibberish_alt_text',
			'Very long — trim'                             => 'alt_too_long',
			'unusually long'                                => 'alt_too_long',
			'exceeds the 125-char SEO sweet spot'          => 'alt_too_long',
			'keyword stuffing'                             => 'keyword_stuffing',
			'special characters'                           => 'special_characters',
			'redundant phrase like "photo of"'              => 'redundant_prefix',
			'identical to the attachment title'             => 'duplicate_alt_title',
			'Lacks descriptive language'                    => 'weak_descriptiveness',
			'not descriptive enough'                        => 'weak_descriptiveness',
			'weak in one or more quality dimensions'        => 'weak_descriptiveness',
		);
	}

	private static function code_for_message( $message ) {
		foreach ( self::issue_code_map() as $needle => $code ) {
			if ( false !== stripos( $message, $needle ) ) {
				return $code;
			}
		}
		return 'weak_alt_text';
	}

	/**
	 * Run a full scan across every image attachment. Free — no AI calls.
	 *
	 * @return array{items_scanned:int,issues_found:int,average_score:int}
	 */
	public function run() {
		$repository = new Scan_Repository( self::MODULE );

		$attachment_ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		if ( empty( $attachment_ids ) ) {
			return array( 'items_scanned' => 0, 'issues_found' => 0, 'average_score' => 0 );
		}

		// First pass: collect every alt text so cross-image duplicates can be
		// detected against the whole scanned set, not just earlier images.
		$alt_by_id     = array();
		$alt_counts    = array();
		foreach ( $attachment_ids as $id ) {
			$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$alt_by_id[ $id ] = $alt;
			if ( '' !== $alt ) {
				$key = self::normalise( $alt );
				$alt_counts[ $key ] = ( $alt_counts[ $key ] ?? 0 ) + 1;
			}
		}

		$items_scanned = 0;
		$issues_found  = 0;
		$item_scores   = array();

		foreach ( $attachment_ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$alt      = $alt_by_id[ $id ];
			$filename = wp_basename( get_attached_file( $id ) ?: '' );
			$context  = array(
				'filename' => $filename,
				'title'    => $post->post_title,
				'caption'  => $post->post_excerpt,
			);

			$result = \BBAI_Alt_Quality_Scorer::score( $alt, $context );
			$score  = (int) $result['score'];

			$issues = array();
			foreach ( (array) $result['issues'] as $message ) {
				// Split the fixed 100-point deficit evenly across however many
				// issues the scorer reported, so a site-wide roll-up still
				// reflects roughly how much each finding costs.
				$deduction = (int) round( ( 100 - $score ) / max( 1, count( $result['issues'] ) ) );
				$severity  = $score <= 20 ? Issue::SEVERITY_CRITICAL : ( $score < 70 ? Issue::SEVERITY_WARNING : Issue::SEVERITY_REVIEW );
				$issues[]  = new Issue( self::code_for_message( $message ), $severity, $deduction, $message );
			}

			// Cross-image duplicate check — the one gap in the existing scorer.
			$dup_count = '' !== $alt ? max( 0, ( $alt_counts[ self::normalise( $alt ) ] ?? 1 ) - 1 ) : 0;
			if ( $dup_count > 0 ) {
				$issues[] = new Issue(
					'duplicate_alt_text',
					Issue::SEVERITY_WARNING,
					20,
					sprintf(
						/* translators: %d: number of other images sharing this alt text. */
						_n(
							'This alt text is identical to %d other image on this site. Unique alt text helps screen-reader users and search engines tell images apart.',
							'This alt text is identical to %d other images on this site. Unique alt text helps screen-reader users and search engines tell images apart.',
							$dup_count,
							'beepbeep-ai-alt-text-generator'
						),
						$dup_count
					),
					array( 'duplicate_count' => $dup_count )
				);
				$score = max( 0, $score - 20 );
			}

			$status = Score_Service::status_for_score( $score );

			$repository->upsert_item(
				(string) $id,
				'image',
				$score,
				$status,
				$issues,
				wp_json_encode( array(
					'alt'      => $alt,
					'filename' => $filename,
					'thumb'    => wp_get_attachment_image_url( $id, 'thumbnail' ),
				) )
			);

			++$items_scanned;
			$issues_found += count( $issues );
			$item_scores[] = $score;
		}

		$average_score = Score_Service::site_score( $item_scores );
		$repository->record_run( $items_scanned, $issues_found, $average_score );

		return array(
			'items_scanned' => $items_scanned,
			'issues_found'  => $issues_found,
			'average_score' => $average_score,
		);
	}

	private static function normalise( $text ) {
		return trim( strtolower( preg_replace( '/\s+/', ' ', (string) $text ) ?? (string) $text ) );
	}
}
