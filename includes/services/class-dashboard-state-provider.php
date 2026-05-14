<?php
/**
 * Single source of truth for dashboard/library credit + coverage state.
 *
 * @package BeepBeep_AI
 */
declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator\Services;

use BeepBeepAI\AltTextGenerator\Core;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dashboard_State_Provider {
	/**
	 * Build the canonical state used by all admin UI surfaces.
	 *
	 * @return array{
	 *   counts: array{missing:int,queued:int,needs_review:int,optimized:int},
	 *   credits: array{used:int,limit:int,remaining:int,has_credit:bool},
	 *   generation: array{in_progress:bool,queue_total:int,queue_remaining:int}
	 * }
	 */
	public static function build( Core $core ): array {
		$coverage = array();
		if ( method_exists( $core, 'get_alt_text_coverage_scan' ) ) {
			// Force fresh counts for UI; avoids stale transient-driven drift.
			$coverage = (array) $core->get_alt_text_coverage_scan( true );
		}

		$missing      = max( 0, (int) ( $coverage['images_missing_alt'] ?? 0 ) );
		$needs_review = max( 0, (int) ( $coverage['needs_review_count'] ?? 0 ) );
		$optimized    = max( 0, (int) ( $coverage['optimized_count'] ?? 0 ) );

		$job = method_exists( $core, 'get_active_job_status' ) ? $core->get_active_job_status() : null;
		$job = is_array( $job ) ? $job : null;

		$in_progress     = false;
		$queue_total     = 0;
		$queue_remaining = 0;
		if ( is_array( $job ) ) {
			$status      = strtolower( (string) ( $job['status'] ?? '' ) );
			$in_progress = in_array( $status, array( 'queued', 'processing' ), true ) && ! empty( $job['active'] );

			$done  = max( 0, (int) ( $job['done'] ?? 0 ) );
			$total = max( 0, (int) ( $job['total'] ?? 0 ) );
			$qc    = max( 0, (int) ( $job['queue_count'] ?? 0 ) );

			$queue_total     = $total;
			$queue_remaining = max( $qc, $total > 0 ? max( 0, $total - $done ) : 0 );
		}

		// "Queued" for UI means "ready to generate". When a job is active, use remaining queue.
		$queued = $in_progress ? $queue_remaining : $missing;

		$usage     = is_callable( array( Usage_Tracker::class, 'get_stats_display' ) ) ? (array) Usage_Tracker::get_stats_display() : array();
		$used      = max( 0, (int) ( $usage['credits_used'] ?? $usage['used'] ?? 0 ) );
		$limit     = max( 1, (int) ( $usage['credits_total'] ?? $usage['limit'] ?? 1 ) );
		$remaining = max( 0, (int) ( $usage['credits_remaining'] ?? $usage['remaining'] ?? max( 0, $limit - $used ) ) );
		$remaining = min( $remaining, $limit );
		$used      = min( $used, $limit );

		return array(
			'counts'     => array(
				'missing'      => $missing,
				'queued'       => $queued,
				'needs_review' => $needs_review,
				'optimized'    => $optimized,
			),
			'credits'    => array(
				'used'       => $used,
				'limit'      => $limit,
				'remaining'  => $remaining,
				'has_credit' => $remaining > 0,
			),
			'generation' => array(
				'in_progress'     => $in_progress,
				'queue_total'     => $queue_total,
				'queue_remaining' => $queue_remaining,
			),
		);
	}
}
