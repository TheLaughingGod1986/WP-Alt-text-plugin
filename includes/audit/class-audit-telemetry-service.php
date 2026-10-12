<?php
/**
 * Audit telemetry.
 *
 * Every property that leaves here is a bucket, an enum or a count — never a
 * URL, file name, alt string, post content or anything else that could identify
 * a person or reveal site content. The whitelist below is the only way a
 * property reaches the wire.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;

/**
 * Privacy-conscious audit events.
 */
class Audit_Telemetry_Service {

	/* ─── Event names ─── */

	const STARTED           = 'image_audit_started';
	const BATCH_COMPLETED   = 'image_audit_batch_completed';
	const COMPLETED         = 'image_audit_completed';
	const FAILED            = 'image_audit_failed';
	const CANCELLED         = 'image_audit_cancelled';
	const RESULTS_VIEWED    = 'image_audit_results_viewed';
	const FILTER_USED       = 'image_audit_filter_used';
	const ACTION_CLICKED    = 'image_issue_action_clicked';
	const MARKED_DECORATIVE = 'image_marked_decorative';
	const ISSUE_RESOLVED    = 'image_issue_resolved';
	const UPGRADE_CTA       = 'image_audit_upgrade_cta_clicked';

	/**
	 * Events fired at most once per run.
	 *
	 * @var array<string,bool>
	 */
	private static $emitted = array();

	/**
	 * Every event this service may send.
	 *
	 * @return string[]
	 */
	public static function events() {
		return array(
			self::STARTED,
			self::BATCH_COMPLETED,
			self::COMPLETED,
			self::FAILED,
			self::CANCELLED,
			self::RESULTS_VIEWED,
			self::FILTER_USED,
			self::ACTION_CLICKED,
			self::MARKED_DECORATIVE,
			self::ISSUE_RESOLVED,
			self::UPGRADE_CTA,
		);
	}

	/**
	 * Properties each event is allowed to carry.
	 *
	 * Anything not listed is dropped, so a future caller cannot accidentally
	 * widen what gets sent.
	 *
	 * @return array<string,string[]>
	 */
	public static function allowed_properties() {
		return array(
			self::STARTED           => array( 'total_images_bucket', 'scan_size_bucket', 'is_resume' ),
			self::BATCH_COMPLETED   => array( 'batch_number', 'batch_size', 'scan_size_bucket' ),
			self::COMPLETED         => array( 'total_images_bucket', 'issue_count_bucket', 'score_bucket', 'audit_duration_bucket', 'error_count_bucket' ),
			self::FAILED            => array( 'batch_number', 'error_type', 'scan_size_bucket' ),
			self::CANCELLED         => array( 'batch_number', 'percent_bucket', 'scan_size_bucket' ),
			self::RESULTS_VIEWED    => array( 'score_bucket', 'issue_count_bucket', 'filter', 'is_limited' ),
			self::FILTER_USED       => array( 'filter', 'result_count_bucket' ),
			self::ACTION_CLICKED    => array( 'action_type', 'issue_type' ),
			self::MARKED_DECORATIVE => array( 'reversed' ),
			self::ISSUE_RESOLVED    => array( 'issue_type', 'resolution' ),
			self::UPGRADE_CTA       => array( 'placement' ),
		);
	}

	/**
	 * Send an event.
	 *
	 * @param string              $event      Event name.
	 * @param array<string,mixed> $properties Raw properties.
	 * @param string              $dedupe_key Optional key; the same key fires once per request.
	 * @return bool Whether the event was sent.
	 */
	public static function emit( $event, array $properties = array(), $dedupe_key = '' ) {
		$event = is_string( $event ) ? $event : '';

		if ( ! in_array( $event, self::events(), true ) ) {
			return false;
		}

		$key = '' !== $dedupe_key ? $event . ':' . $dedupe_key : $event;

		if ( isset( self::$emitted[ $key ] ) ) {
			return false;
		}

		self::$emitted[ $key ] = true;

		if ( ! function_exists( '\BeepBeepAI\AltTextGenerator\bbai_telemetry_emit' ) && ! class_exists( '\BeepBeepAI\AltTextGenerator\BBAI_Telemetry' ) ) {
			return false;
		}

		BBAI_Telemetry::emit( $event, self::sanitize( $event, $properties ) );

		return true;
	}

	/**
	 * Strip anything the event is not allowed to carry, and flatten what remains
	 * to scalars.
	 *
	 * @param string              $event      Event name.
	 * @param array<string,mixed> $properties Raw properties.
	 * @return array<string,scalar>
	 */
	public static function sanitize( $event, array $properties ) {
		$allowed = self::allowed_properties();
		$keys    = isset( $allowed[ $event ] ) ? $allowed[ $event ] : array();
		$clean   = array();

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $properties ) ) {
				continue;
			}

			$value = $properties[ $key ];

			if ( is_bool( $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				// Enums and buckets only — never free text.
				$clean[ $key ] = substr( sanitize_key( $value ), 0, 40 );
			}
		}

		return $clean;
	}

	/**
	 * Reset the de-duplication guard. Test helper.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$emitted = array();
	}

	/**
	 * How often batch-progress events are worth reporting.
	 *
	 * Emitting once per batch would tie telemetry cost to library size: a 10,000
	 * image scan runs 100 batches, and every emit currently resolves plan data,
	 * which is not free. Sampling keeps the "where did a scan stall" signal while
	 * holding the number of events flat as libraries grow.
	 */
	const BATCH_EVENT_INTERVAL = 25;

	/**
	 * Whether a given batch should report progress.
	 *
	 * Always reports the first batch — that is the one that proves a scan got
	 * past setup — then samples.
	 *
	 * @param int $batch_number 1-based batch number.
	 * @return bool
	 */
	public static function should_report_batch( $batch_number ) {
		$batch_number = (int) $batch_number;

		if ( $batch_number < 1 ) {
			return false;
		}

		if ( 1 === $batch_number ) {
			return true;
		}

		/**
		 * Filter how often audit batch progress is reported.
		 *
		 * @since 4.8.0
		 *
		 * @param int $interval Report every Nth batch.
		 */
		$interval = (int) apply_filters( 'bbai_audit_batch_event_interval', self::BATCH_EVENT_INTERVAL );

		return $interval > 0 && 0 === $batch_number % $interval;
	}

	/* ─── Buckets ─── */

	/**
	 * Library-size bucket.
	 *
	 * @param int $count Image count.
	 * @return string
	 */
	public static function size_bucket( $count ) {
		$count = max( 0, (int) $count );

		if ( 0 === $count ) {
			return '0';
		}
		if ( $count <= 50 ) {
			return '1_50';
		}
		if ( $count <= 500 ) {
			return '51_500';
		}
		if ( $count <= 5000 ) {
			return '501_5000';
		}

		return '5000_plus';
	}

	/**
	 * Issue-count bucket.
	 *
	 * @param int $count Issue count.
	 * @return string
	 */
	public static function issue_bucket( $count ) {
		$count = max( 0, (int) $count );

		if ( 0 === $count ) {
			return '0';
		}
		if ( $count <= 10 ) {
			return '1_10';
		}
		if ( $count <= 100 ) {
			return '11_100';
		}
		if ( $count <= 1000 ) {
			return '101_1000';
		}

		return '1000_plus';
	}

	/**
	 * Score band, delegating to the score service so the bands cannot drift.
	 *
	 * @param int $score Score 0–100.
	 * @return string
	 */
	public static function score_bucket( $score ) {
		return str_replace( '-', '_', Image_Accessibility_Score_Service::band( $score ) );
	}

	/**
	 * Duration bucket in seconds.
	 *
	 * @param int $seconds Elapsed seconds.
	 * @return string
	 */
	public static function duration_bucket( $seconds ) {
		$seconds = max( 0, (int) $seconds );

		if ( $seconds < 5 ) {
			return 'under_5s';
		}
		if ( $seconds <= 30 ) {
			return '5_30s';
		}
		if ( $seconds <= 120 ) {
			return '30_120s';
		}
		if ( $seconds <= 600 ) {
			return '2_10m';
		}

		return 'over_10m';
	}

	/**
	 * Percentage bucket, in tens.
	 *
	 * @param int $percent Percentage 0–100.
	 * @return string
	 */
	public static function percent_bucket( $percent ) {
		$percent = max( 0, min( 100, (int) $percent ) );

		return (string) ( 10 * (int) floor( $percent / 10 ) );
	}
}
