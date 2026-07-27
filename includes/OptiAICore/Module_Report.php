<?php
/**
 * The standard cross-module metrics shape a future OptiAI Hub aggregates.
 *
 * Every module exposes its own health via the `optiai_module_report` filter
 * instead of a hard dependency on a Hub plugin — if a Hub is active later it
 * simply calls `apply_filters( 'optiai_module_report', array(), 'titles' )`
 * on each known module slug and renders the combined view. No plugin needs
 * to know the Hub exists for this to work.
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core;

use OptiAI\Core\Scan\Scan_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module_Report {

	/**
	 * Build the standard report shape for one module and expose it via the
	 * shared filter. Call this once from each module's dashboard bootstrap.
	 *
	 * @param string $module Module slug.
	 * @param string $name   Display name shown in a future Hub's module list.
	 * @return void
	 */
	public static function expose( $module, $name ) {
		add_filter( 'optiai_module_report', static function ( $report, $requested_module ) use ( $module, $name ) {
			if ( $requested_module !== $module ) {
				return $report;
			}
			return self::build( $module, $name );
		}, 10, 2 );
	}

	/**
	 * @param string $module Module slug.
	 * @param string $name   Display name.
	 * @return array{module:string,name:string,score:int,status:string,critical_issues:int,items_improved:int,items_remaining:int,last_scan:?string,trend:?int}
	 */
	public static function build( $module, $name ) {
		$repo    = new Scan_Repository( $module );
		$summary = $repo->get_summary();
		$prev    = $repo->get_previous_score();

		return array(
			'module'          => $module,
			'name'            => $name,
			'score'           => $summary['score'],
			'status'          => $summary['status'],
			'critical_issues' => $summary['critical'],
			'items_improved'  => $summary['optimised_this_week'],
			'items_remaining' => max( 0, $summary['total'] - array_sum( array( $summary['by_status']['excellent'] ) ) ),
			'last_scan'       => $summary['last_scanned_at'],
			'trend'           => Health_Score::trend( $summary['score'], $prev ),
		);
	}
}
