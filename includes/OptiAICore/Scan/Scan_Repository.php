<?php
/**
 * Reads and writes one module's rows in the shared scan-storage tables.
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core\Scan;

use OptiAI\Core\Scoring\Issue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scan_Repository {

	/** @var string Module slug this repository is scoped to, e.g. "titles". */
	private $module;

	/**
	 * @param string $module Module slug.
	 */
	public function __construct( $module ) {
		$this->module = (string) $module;
	}

	/**
	 * Insert or update one item's scan result.
	 *
	 * @param string     $site_item_id  WP-side id (post ID / attachment ID) as string.
	 * @param string     $item_type     e.g. "post", "page", "product", "image".
	 * @param int        $score         0-100.
	 * @param string     $status        Score_Service::status_for_score() band.
	 * @param Issue[]    $issues        Issues found on this item.
	 * @param string     $current_value Snapshot of the current title/meta/alt text, for display.
	 * @return void
	 */
	public function upsert_item( $site_item_id, $item_type, $score, $status, array $issues, $current_value = '' ) {
		global $wpdb;

		if ( ! Schema::items_table_exists() ) {
			return;
		}

		$now          = current_time( 'mysql' );
		$issues_array = array_map(
			static function ( Issue $issue ) {
				return $issue->to_array();
			},
			$issues
		);
		$content_hash = md5( (string) $current_value );

		$table = Schema::items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- values bound via prepare() below.
		$existing_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE module = %s AND site_item_id = %s AND item_type = %s",
			$this->module,
			$site_item_id,
			$item_type
		) );

		$data = array(
			'module'          => $this->module,
			'site_item_id'    => (string) $site_item_id,
			'item_type'       => (string) $item_type,
			'score'           => max( 0, min( 100, (int) $score ) ),
			'status'          => (string) $status,
			'issues_json'     => wp_json_encode( $issues_array ),
			'current_value'   => (string) $current_value,
			'content_hash'    => $content_hash,
			'last_scanned_at' => $now,
			'updated_at'      => $now,
		);

		if ( $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- $wpdb->update() escapes/prepares internally.
			$wpdb->update( $table, $data, array( 'id' => (int) $existing_id ) );
			return;
		}

		$data['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- $wpdb->insert() escapes/prepares internally.
		$wpdb->insert( $table, $data );
	}

	/**
	 * Mark an item as freshly optimised (called after a successful AI fix
	 * is saved, separately from the next full rescan).
	 *
	 * @param string $site_item_id WP-side id.
	 * @param string $item_type    Item type.
	 * @return void
	 */
	public function mark_optimised( $site_item_id, $item_type ) {
		global $wpdb;
		if ( ! Schema::items_table_exists() ) {
			return;
		}
		$table = Schema::items_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- $wpdb->update() escapes/prepares internally.
		$wpdb->update(
			$table,
			array( 'last_optimised_at' => current_time( 'mysql' ) ),
			array(
				'module'       => $this->module,
				'site_item_id' => (string) $site_item_id,
				'item_type'    => (string) $item_type,
			)
		);
	}

	/**
	 * Site-level summary for this module's Hero Score + Summary Cards.
	 *
	 * @return array{total:int,score:int,status:string,by_status:array,critical:int,optimised_this_week:int,last_scanned_at:?string}
	 */
	public function get_summary() {
		global $wpdb;

		$empty = array(
			'total'               => 0,
			'score'               => 0,
			'status'              => 'unknown',
			'by_status'           => array(
				'excellent'         => 0,
				'good'              => 0,
				'fair'              => 0,
				'needs-improvement' => 0,
				'critical'          => 0,
			),
			'critical'            => 0,
			'optimised_this_week' => 0,
			'last_scanned_at'     => null,
		);

		if ( ! Schema::items_table_exists() ) {
			return $empty;
		}

		$table = Schema::items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- value bound via prepare().
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT score, status FROM {$table} WHERE module = %s",
			$this->module
		), ARRAY_A );

		if ( empty( $rows ) ) {
			return $empty;
		}

		$scores    = array_map( static fn( $r ) => (int) $r['score'], $rows );
		$by_status = $empty['by_status'];
		foreach ( $rows as $row ) {
			$status = $row['status'];
			if ( isset( $by_status[ $status ] ) ) {
				++$by_status[ $status ];
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- value bound via prepare().
		$last_scanned = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(last_scanned_at) FROM {$table} WHERE module = %s",
			$this->module
		) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- value bound via prepare().
		$optimised_this_week = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE module = %s AND last_optimised_at >= %s",
			$this->module,
			gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
		) );

		$total_deduction = array_sum( array_map( static fn( $s ) => 100 - $s, $scores ) );
		$site_score      = (int) round( max( 0, min( 100, 100 - ( $total_deduction / count( $scores ) ) ) ) );

		return array(
			'total'               => count( $rows ),
			'score'               => $site_score,
			'status'              => \OptiAI\Core\Scoring\Score_Service::status_for_score( $site_score ),
			'by_status'           => $by_status,
			'critical'            => $by_status['critical'],
			'optimised_this_week' => $optimised_this_week,
			'last_scanned_at'     => $last_scanned,
		);
	}

	/**
	 * Aggregate issues across every scanned item into "priority issue"
	 * groups (one card per issue code) for the Priority Action Centre /
	 * Today's Priorities banner.
	 *
	 * @param int $limit Max number of groups to return.
	 * @return array<int,array{code:string,severity:string,count:int,message:string,estimated_gain:int}>
	 */
	public function get_priority_issues( $limit = 5 ) {
		global $wpdb;

		if ( ! Schema::items_table_exists() ) {
			return array();
		}

		$table = Schema::items_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- value bound via prepare().
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT issues_json FROM {$table} WHERE module = %s AND issues_json IS NOT NULL AND issues_json != '[]'",
			$this->module
		), ARRAY_A );

		$groups = array();
		foreach ( $rows as $row ) {
			$issues = json_decode( (string) $row['issues_json'], true );
			if ( ! is_array( $issues ) ) {
				continue;
			}
			foreach ( $issues as $issue ) {
				$code = isset( $issue['code'] ) ? $issue['code'] : '';
				if ( '' === $code ) {
					continue;
				}
				if ( ! isset( $groups[ $code ] ) ) {
					$groups[ $code ] = array(
						'code'           => $code,
						'severity'       => isset( $issue['severity'] ) ? $issue['severity'] : 'information',
						'count'          => 0,
						'message'        => isset( $issue['message'] ) ? $issue['message'] : '',
						'total_deduction' => 0,
					);
				}
				++$groups[ $code ]['count'];
				$groups[ $code ]['total_deduction'] += isset( $issue['deduction'] ) ? (int) $issue['deduction'] : 0;
			}
		}

		$severity_rank = array(
			'critical'    => 4,
			'warning'     => 3,
			'review'      => 2,
			'information' => 1,
		);

		usort( $groups, static function ( $a, $b ) use ( $severity_rank ) {
			$rank_a = isset( $severity_rank[ $a['severity'] ] ) ? $severity_rank[ $a['severity'] ] : 0;
			$rank_b = isset( $severity_rank[ $b['severity'] ] ) ? $severity_rank[ $b['severity'] ] : 0;
			if ( $rank_a !== $rank_b ) {
				return $rank_b - $rank_a;
			}
			return $b['count'] - $a['count'];
		} );

		$total_scanned = (int) $wpdb->get_var( $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table is prefix-built.
			"SELECT COUNT(*) FROM {$table} WHERE module = %s",
			$this->module
		) );
		$total_scanned = max( 1, $total_scanned );

		$out = array();
		foreach ( array_slice( $groups, 0, max( 0, (int) $limit ) ) as $group ) {
			$out[] = array(
				'code'            => $group['code'],
				'severity'        => $group['severity'],
				'count'           => $group['count'],
				'message'         => $group['message'],
				'estimated_gain'  => (int) round( ( $group['total_deduction'] / $total_scanned ) ),
			);
		}
		return $out;
	}

	/**
	 * Paginated, filterable, sortable item list for the Advanced Library view.
	 *
	 * @param array $args {
	 *     @type string $status  Filter by status band, or '' for all.
	 *     @type string $sort    lowest-score|highest-score|most-issues|newest|oldest|recently-optimised.
	 *     @type int    $page    1-based page number.
	 *     @type int    $per_page Page size.
	 * }
	 * @return array{items:array,total:int}
	 */
	public function get_items( array $args = array() ) {
		global $wpdb;

		if ( ! Schema::items_table_exists() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$status   = isset( $args['status'] ) ? (string) $args['status'] : '';
		$sort     = isset( $args['sort'] ) ? (string) $args['sort'] : 'lowest-score';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table       = Schema::items_table();
		$where       = 'WHERE module = %s';
		$where_args  = array( $this->module );
		if ( '' !== $status ) {
			$where      .= ' AND status = %s';
			$where_args[] = $status;
		}

		$order_by = 'score ASC';
		switch ( $sort ) {
			case 'highest-score':
				$order_by = 'score DESC';
				break;
			case 'newest':
				$order_by = 'created_at DESC';
				break;
			case 'oldest':
				$order_by = 'created_at ASC';
				break;
			case 'recently-optimised':
				$order_by = 'last_optimised_at DESC';
				break;
			case 'lowest-score':
			default:
				$order_by = 'score ASC';
				break;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $table/$order_by are internal, values bound via prepare().
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} {$where}",
			$where_args
		) );

		$query_args   = $where_args;
		$query_args[] = $per_page;
		$query_args[] = $offset;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} {$where} ORDER BY {$order_by} LIMIT %d OFFSET %d",
			$query_args
		), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$items = array_map( static function ( $row ) {
			$row['issues'] = json_decode( (string) $row['issues_json'], true ) ?: array();
			unset( $row['issues_json'] );
			return $row;
		}, is_array( $rows ) ? $rows : array() );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Record a completed scan run (for "last scan date" + score trend).
	 *
	 * @param int $items_scanned Items scanned in this run.
	 * @param int $issues_found  Total issue count found.
	 * @param int $average_score Site score after this run.
	 * @return void
	 */
	public function record_run( $items_scanned, $issues_found, $average_score ) {
		global $wpdb;
		if ( ! Schema::items_table_exists() ) {
			return;
		}
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- $wpdb->insert() escapes/prepares internally.
		$wpdb->insert( Schema::runs_table(), array(
			'module'         => $this->module,
			'status'         => 'completed',
			'items_scanned'  => (int) $items_scanned,
			'issues_found'   => (int) $issues_found,
			'average_score'  => (int) $average_score,
			'started_at'     => $now,
			'completed_at'   => $now,
		) );
	}

	/**
	 * The score from the previous completed run, for the Hero Score's trend
	 * arrow. Null when there is no prior run (first scan).
	 *
	 * @return int|null
	 */
	public function get_previous_score() {
		global $wpdb;
		if ( ! Schema::items_table_exists() ) {
			return null;
		}
		$table = Schema::runs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- value bound via prepare().
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT average_score FROM {$table} WHERE module = %s ORDER BY completed_at DESC LIMIT 2",
			$this->module
		), ARRAY_A );
		if ( count( $rows ) < 2 ) {
			return null;
		}
		return (int) $rows[1]['average_score'];
	}
}
