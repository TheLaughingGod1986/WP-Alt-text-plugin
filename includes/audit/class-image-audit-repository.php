<?php
/**
 * Storage for per-image audit findings.
 *
 * One row per attachment per site identifier, upserted on every scan, so a
 * rescan updates in place rather than growing history.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\DB_Schema;

/**
 * Audit table gateway.
 */
class Image_Audit_Repository {

	const TABLE_SLUG = 'bbai_image_audit';

	/* ─── Row statuses ─── */

	const STATUS_OPEN       = 'open';
	const STATUS_RESOLVED   = 'resolved';
	const STATUS_DISMISSED  = 'dismissed';
	const STATUS_DECORATIVE = 'decorative';

	/**
	 * Statuses that count as settled — no longer awaiting action.
	 *
	 * @return string[]
	 */
	public static function settled_statuses() {
		return array( self::STATUS_RESOLVED, self::STATUS_DISMISSED, self::STATUS_DECORATIVE );
	}

	/**
	 * All valid statuses.
	 *
	 * @return string[]
	 */
	public static function all_statuses() {
		return array( self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_DISMISSED, self::STATUS_DECORATIVE );
	}

	/**
	 * Whether a status is one this plugin understands.
	 *
	 * @param string $status Status slug.
	 * @return bool
	 */
	public static function is_valid_status( $status ) {
		return is_string( $status ) && in_array( $status, self::all_statuses(), true );
	}

	/**
	 * Fully-prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_SLUG;
	}

	/**
	 * Create or migrate the table.
	 *
	 * Runs through dbDelta so repeat calls are no-ops, which is what makes the
	 * schema upgrade idempotent.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table   = esc_sql( self::table() );
		$charset = $wpdb->get_charset_collate();

		$sql = "
			CREATE TABLE `{$table}` (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				attachment_id BIGINT UNSIGNED NOT NULL,
				site_id VARCHAR(64) NOT NULL DEFAULT '',
				run_id VARCHAR(36) NOT NULL DEFAULT '',
				current_alt_hash CHAR(40) NOT NULL DEFAULT '',
				alt_length SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				issue_codes VARCHAR(255) NOT NULL DEFAULT '',
				issue_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
				severity VARCHAR(20) NOT NULL DEFAULT 'information',
				audit_status VARCHAR(20) NOT NULL DEFAULT 'open',
				score_contribution DECIMAL(5,2) NOT NULL DEFAULT 0.00,
				duplicate_group_size SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				context_summary VARCHAR(191) NOT NULL DEFAULT '',
				parent_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				last_scanned_at DATETIME NULL,
				resolved_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY attachment_site (attachment_id, site_id),
				KEY severity_status (severity, audit_status),
				KEY audit_status (audit_status),
				KEY last_scanned_at (last_scanned_at),
				KEY run_id (run_id)
			) {$charset};
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		dbDelta( $sql );
	}

	/**
	 * Whether the audit table exists.
	 *
	 * Callers use this to degrade gracefully — an install where table creation
	 * failed should show an actionable error, not a fatal.
	 *
	 * @param bool $force_refresh Skip the per-request cache.
	 * @return bool
	 */
	public static function table_exists( $force_refresh = false ) {
		static $exists = null;

		if ( null === $exists || $force_refresh ) {
			$exists = class_exists( '\BeepBeepAI\AltTextGenerator\DB_Schema' )
				? DB_Schema::table_exists( self::table() )
				: false;
		}

		return (bool) $exists;
	}

	/**
	 * Identifier for this site, matching the convention used by the usage logs.
	 *
	 * @return string
	 */
	public static function site_id() {
		// The helper lives in the parent namespace, so it must be referenced by its
		// fully-qualified name — an unqualified call from here resolves to nothing
		// and would silently store every row against an empty site identifier.
		$helper_function = '\BeepBeepAI\AltTextGenerator\get_site_identifier';

		if ( ! function_exists( $helper_function ) ) {
			$helper = defined( 'BEEPBEEP_AI_PLUGIN_DIR' )
				? BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php'
				: '';

			if ( '' !== $helper && file_exists( $helper ) ) {
				require_once $helper;
			}
		}

		return function_exists( $helper_function ) ? (string) call_user_func( $helper_function ) : '';
	}

	/**
	 * Turn a rule-evaluation result into a storable row.
	 *
	 * @param Image_Audit_Context $context Image that was audited.
	 * @param array               $result  Registry evaluation result.
	 * @param string              $run_id  Run identifier.
	 * @return array<string,mixed>
	 */
	public static function build_record( Image_Audit_Context $context, array $result, $run_id ) {
		$codes    = isset( $result['codes'] ) && is_array( $result['codes'] ) ? $result['codes'] : array();
		$severity = isset( $result['severity'] ) && Audit_Issue::is_valid_severity( $result['severity'] )
			? $result['severity']
			: Audit_Issue::SEVERITY_INFORMATION;

		// A decorative decision is a settled outcome, not an outstanding issue.
		if ( $context->is_decorative() ) {
			$status = self::STATUS_DECORATIVE;
		} elseif ( empty( $codes ) ) {
			$status = self::STATUS_RESOLVED;
		} else {
			$status = self::STATUS_OPEN;
		}

		return array(
			'attachment_id'        => $context->get_attachment_id(),
			'run_id'               => (string) $run_id,
			'current_alt_hash'     => $context->get_alt_hash(),
			'alt_length'           => min( 65535, $context->get_alt_length() ),
			'issue_codes'          => Audit_Issue::encode_codes( $codes ),
			'issue_count'          => min( 255, count( $codes ) ),
			'severity'             => $severity,
			'audit_status'         => $status,
			'score_contribution'   => self::score_contribution( $status, $severity ),
			'duplicate_group_size' => min( 65535, $context->get_duplicate_group_size() ),
			'context_summary'      => $context->get_context_summary(),
			'parent_post_id'       => $context->get_parent_post_id(),
		);
	}

	/**
	 * Per-image deduction, mirroring the score service weights.
	 *
	 * Stored so a row can explain its own contribution without recomputing the
	 * whole site score.
	 *
	 * @param string $status   Audit status.
	 * @param string $severity Worst severity.
	 * @return float
	 */
	public static function score_contribution( $status, $severity ) {
		if ( in_array( $status, self::settled_statuses(), true ) ) {
			return 0.0;
		}

		$weights = Image_Accessibility_Score_Service::get_weights();

		return isset( $weights[ $severity ] ) ? (float) $weights[ $severity ] : 0.0;
	}

	/**
	 * Insert or update many rows in one statement.
	 *
	 * Upserting on the (attachment_id, site_id) unique key keeps exactly one row
	 * per image, so a rescan updates in place instead of accumulating history.
	 *
	 * @param array<int,array<string,mixed>> $records Records from build_record().
	 * @return int Rows written.
	 */
	public static function upsert_many( array $records ) {
		global $wpdb;

		if ( empty( $records ) ) {
			return 0;
		}

		$table   = esc_sql( self::table() );
		$site_id = self::site_id();
		$now     = current_time( 'mysql' );

		$placeholders = array();
		$values       = array();

		foreach ( $records as $record ) {
			$attachment_id = isset( $record['attachment_id'] ) ? (int) $record['attachment_id'] : 0;
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$status      = isset( $record['audit_status'] ) && self::is_valid_status( $record['audit_status'] )
				? $record['audit_status']
				: self::STATUS_OPEN;
			$resolved_at = in_array( $status, self::settled_statuses(), true ) ? $now : null;

			$placeholders[] = '(%d, %s, %s, %s, %d, %s, %d, %s, %s, %f, %d, %s, %d, %s, %s, %s, %s)';

			$values[] = $attachment_id;
			$values[] = $site_id;
			$values[] = isset( $record['run_id'] ) ? (string) $record['run_id'] : '';
			$values[] = isset( $record['current_alt_hash'] ) ? (string) $record['current_alt_hash'] : '';
			$values[] = isset( $record['alt_length'] ) ? (int) $record['alt_length'] : 0;
			$values[] = isset( $record['issue_codes'] ) ? (string) $record['issue_codes'] : '';
			$values[] = isset( $record['issue_count'] ) ? (int) $record['issue_count'] : 0;
			$values[] = isset( $record['severity'] ) ? (string) $record['severity'] : Audit_Issue::SEVERITY_INFORMATION;
			$values[] = $status;
			$values[] = isset( $record['score_contribution'] ) ? (float) $record['score_contribution'] : 0.0;
			$values[] = isset( $record['duplicate_group_size'] ) ? (int) $record['duplicate_group_size'] : 0;
			$values[] = isset( $record['context_summary'] ) ? (string) $record['context_summary'] : '';
			$values[] = isset( $record['parent_post_id'] ) ? (int) $record['parent_post_id'] : 0;
			$values[] = $now;          // last_scanned_at.
			$values[] = $resolved_at;  // resolved_at.
			$values[] = $now;          // created_at.
			$values[] = $now;          // updated_at.
		}

		if ( empty( $placeholders ) ) {
			return 0;
		}

		$sql = 'INSERT INTO `' . $table . '`
				(attachment_id, site_id, run_id, current_alt_hash, alt_length, issue_codes, issue_count,
				 severity, audit_status, score_contribution, duplicate_group_size, context_summary,
				 parent_post_id, last_scanned_at, resolved_at, created_at, updated_at)
				VALUES ' . implode( ', ', $placeholders ) . '
				ON DUPLICATE KEY UPDATE
					run_id = VALUES(run_id),
					current_alt_hash = VALUES(current_alt_hash),
					alt_length = VALUES(alt_length),
					issue_codes = VALUES(issue_codes),
					issue_count = VALUES(issue_count),
					severity = VALUES(severity),
					audit_status = VALUES(audit_status),
					score_contribution = VALUES(score_contribution),
					duplicate_group_size = VALUES(duplicate_group_size),
					context_summary = VALUES(context_summary),
					parent_post_id = VALUES(parent_post_id),
					last_scanned_at = VALUES(last_scanned_at),
					resolved_at = VALUES(resolved_at),
					updated_at = VALUES(updated_at)';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Identifier is schema-controlled; every value is bound.
		$written = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return is_numeric( $written ) ? (int) $written : 0;
	}

	/**
	 * Severity and status tallies for the whole site.
	 *
	 * One grouped query. Used to build the authoritative summary at the end of a
	 * run, rather than trusting counters accumulated across resumed batches.
	 *
	 * @return array<string,int>
	 */
	public static function get_counters() {
		global $wpdb;

		$counters = array(
			'scanned_images' => 0,
			'critical'       => 0,
			'warning'        => 0,
			'review'         => 0,
			'information'    => 0,
			'settled'        => 0,
			'open'           => 0,
		);

		if ( ! self::table_exists() ) {
			return $counters;
		}

		$table = esc_sql( self::table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate is cached by the caller.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is schema-controlled and escaped with esc_sql(); all values are bound.
				'SELECT severity, audit_status, COUNT(*) AS cnt FROM `' . $table . '` WHERE site_id = %s GROUP BY severity, audit_status',
				self::site_id()
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return $counters;
		}

		$settled = self::settled_statuses();

		foreach ( $rows as $row ) {
			$count    = (int) $row['cnt'];
			$severity = (string) $row['severity'];
			$status   = (string) $row['audit_status'];

			$counters['scanned_images'] += $count;

			if ( in_array( $status, $settled, true ) ) {
				$counters['settled'] += $count;
				continue;
			}

			$counters['open'] += $count;

			if ( isset( $counters[ $severity ] ) ) {
				$counters[ $severity ] += $count;
			}
		}

		return $counters;
	}

	/**
	 * How many images carry a given issue code.
	 *
	 * The stored codes are delimited (",missing_alt,"), so a LIKE on the wrapped
	 * code cannot collide with a code that merely shares a prefix.
	 *
	 * @param string $code Issue code.
	 * @return int
	 */
	public static function count_by_issue( $code ) {
		global $wpdb;

		if ( ! self::table_exists() || null === Audit_Issue::definition( $code ) ) {
			return 0;
		}

		$table = esc_sql( self::table() );
		$like  = '%' . $wpdb->esc_like( ',' . $code . ',' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate is cached by the caller.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is schema-controlled and escaped with esc_sql(); all values are bound.
				'SELECT COUNT(*) FROM `' . $table . '` WHERE site_id = %s AND audit_status = %s AND issue_codes LIKE %s',
				self::site_id(),
				self::STATUS_OPEN,
				$like
			)
		);
	}

	/**
	 * Count images carrying any of a set of issue codes, without double-counting.
	 *
	 * An image with both `filename_as_alt` and `alt_too_short` is one image with
	 * weak alt text, not two.
	 *
	 * @param string[] $codes Issue codes.
	 * @return int
	 */
	public static function count_by_any_issue( array $codes ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$conditions = array();
		$values     = array( self::site_id(), self::STATUS_OPEN );

		foreach ( $codes as $code ) {
			if ( null === Audit_Issue::definition( $code ) ) {
				continue;
			}

			$conditions[] = 'issue_codes LIKE %s';
			$values[]     = '%' . $wpdb->esc_like( ',' . $code . ',' ) . '%';
		}

		if ( empty( $conditions ) ) {
			return 0;
		}

		$table = esc_sql( self::table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate is cached by the caller.
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One placeholder per supplied code; the value list is built alongside them.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is schema-controlled and escaped with esc_sql(); all values are bound.
				'SELECT COUNT(DISTINCT attachment_id) FROM `' . $table . '` WHERE site_id = %s AND audit_status = %s AND (' . implode( ' OR ', $conditions ) . ')',
				$values
			)
		);
	}

	/**
	 * Change a row's status, for example when a finding is dismissed.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $status        New status.
	 * @return bool
	 */
	public static function set_status( $attachment_id, $status ) {
		global $wpdb;

		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 || ! self::is_valid_status( $status ) || ! self::table_exists() ) {
			return false;
		}

		$now      = current_time( 'mysql' );
		$settled  = in_array( $status, self::settled_statuses(), true );
		$severity = $settled ? Audit_Issue::SEVERITY_INFORMATION : null;

		$data = array(
			'audit_status'       => $status,
			'score_contribution' => self::score_contribution( $status, Audit_Issue::SEVERITY_INFORMATION ),
			'resolved_at'        => $settled ? $now : null,
			'updated_at'         => $now,
		);

		$formats = array( '%s', '%f', '%s', '%s' );

		if ( null !== $severity ) {
			$data['severity'] = $severity;
			$formats[]        = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write of audit state.
		$updated = $wpdb->update(
			self::table(),
			$data,
			array(
				'attachment_id' => $attachment_id,
				'site_id'       => self::site_id(),
			),
			$formats,
			array( '%d', '%s' )
		);

		return false !== $updated;
	}

	/**
	 * Fetch one row.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_row( $attachment_id ) {
		global $wpdb;

		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 || ! self::table_exists() ) {
			return null;
		}

		$table = esc_sql( self::table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-row read of live audit state.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is schema-controlled and escaped with esc_sql(); all values are bound.
				'SELECT * FROM `' . $table . '` WHERE attachment_id = %d AND site_id = %s LIMIT 1',
				$attachment_id,
				self::site_id()
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Remove the row for an attachment, for example when it is deleted.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function delete_row( $attachment_id ) {
		global $wpdb;

		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 || ! self::table_exists() ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct delete of audit state.
		return (bool) $wpdb->delete(
			self::table(),
			array(
				'attachment_id' => $attachment_id,
				'site_id'       => self::site_id(),
			),
			array( '%d', '%s' )
		);
	}

	/**
	 * Build the WHERE fragment for a filter, as SQL plus bound values.
	 *
	 * Issue codes are matched against the stored delimited string, so a search for
	 * ",generic_alt," cannot accidentally match a longer code.
	 *
	 * @param string $filter Filter slug.
	 * @return array{sql:string,values:array}
	 */
	private static function filter_clause( $filter ) {
		global $wpdb;

		$open     = ' AND audit_status = %s';
		$settled  = self::settled_statuses();
		$like_all = static function ( array $codes ) use ( $wpdb ) {
			$parts  = array();
			$values = array();

			foreach ( $codes as $code ) {
				$parts[]  = 'issue_codes LIKE %s';
				$values[] = '%' . $wpdb->esc_like( ',' . $code . ',' ) . '%';
			}

			return array(
				'sql'    => empty( $parts ) ? '' : ' AND (' . implode( ' OR ', $parts ) . ')',
				'values' => $values,
			);
		};

		switch ( $filter ) {
			case Audit_Filters::MISSING_ALT:
				$codes = $like_all( array( Audit_Issue::MISSING_ALT ) );
				return array(
					'sql'    => $open . $codes['sql'],
					'values' => array_merge( array( self::STATUS_OPEN ), $codes['values'] ),
				);

			case Audit_Filters::WEAK_ALT:
				$codes = $like_all( Audit_Filters::weak_alt_codes() );
				return array(
					'sql'    => $open . $codes['sql'],
					'values' => array_merge( array( self::STATUS_OPEN ), $codes['values'] ),
				);

			case Audit_Filters::DUPLICATE_ALT:
				$codes = $like_all( array( Audit_Issue::DUPLICATE_ALT ) );
				return array(
					'sql'    => $open . $codes['sql'],
					'values' => array_merge( array( self::STATUS_OPEN ), $codes['values'] ),
				);

			case Audit_Filters::REVIEW_REQUIRED:
				$codes = $like_all( Audit_Filters::review_required_codes() );
				return array(
					'sql'    => $open . $codes['sql'],
					'values' => array_merge( array( self::STATUS_OPEN ), $codes['values'] ),
				);

			case Audit_Filters::CRITICAL:
				return array(
					'sql'    => $open . ' AND severity = %s',
					'values' => array( self::STATUS_OPEN, Audit_Issue::SEVERITY_CRITICAL ),
				);

			case Audit_Filters::WARNING:
				return array(
					'sql'    => $open . ' AND severity = %s',
					'values' => array( self::STATUS_OPEN, Audit_Issue::SEVERITY_WARNING ),
				);

			case Audit_Filters::INFORMATION:
				return array(
					'sql'    => $open . ' AND severity = %s',
					'values' => array( self::STATUS_OPEN, Audit_Issue::SEVERITY_INFORMATION ),
				);

			case Audit_Filters::RESOLVED:
				$placeholders = implode( ', ', array_fill( 0, count( $settled ), '%s' ) );
				return array(
					'sql'    => ' AND audit_status IN (' . $placeholders . ')',
					'values' => $settled,
				);

			case Audit_Filters::ALL:
			default:
				/*
				 * "Needs attention" means work, not every note. An image whose only
				 * finding is informational — typically "not attached to any post" —
				 * cannot be cleared by writing better alt text, so leaving it in the
				 * default list would mean the count never reaches zero however much
				 * the user fixes. Those rows live under the Information filter.
				 */
				return array(
					'sql'    => $open . ' AND issue_codes <> %s AND severity <> %s',
					'values' => array( self::STATUS_OPEN, '', Audit_Issue::SEVERITY_INFORMATION ),
				);
		}
	}

	/**
	 * One page of audit findings.
	 *
	 * @param array<string,mixed> $args {
	 *     @type string $filter   Filter slug.
	 *     @type int    $page     1-based page number.
	 *     @type int    $per_page Rows per page, clamped 1–100.
	 * }
	 * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
	 */
	public static function query_issues( array $args = array() ) {
		global $wpdb;

		$filter   = Audit_Filters::sanitize( $args['filter'] ?? Audit_Filters::ALL );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

		$empty = array(
			'rows'        => array(),
			'total'       => 0,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => 0,
			'filter'      => $filter,
		);

		if ( ! self::table_exists() ) {
			return $empty;
		}

		$table  = esc_sql( self::table() );
		$clause = self::filter_clause( $filter );
		$where  = 'WHERE site_id = %s' . $clause['sql'];
		$base   = array_merge( array( self::site_id() ), $clause['values'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated read of live audit state.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where is assembled from filter_clause(), whose placeholders and $base values line up positionally; asserted by AuditFiltersTest::test_every_filtered_query_binds_all_of_its_placeholders().
				'SELECT COUNT(*) FROM `' . $table . '` ' . $where,
				$base
			)
		);

		if ( $total <= 0 ) {
			return $empty;
		}

		$total_pages = (int) ceil( $total / $per_page );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $per_page;

		/*
		 * Most serious first, then most recently scanned: the ordering a person
		 * needs when the question is "what do I fix first".
		 */
		$order = ' ORDER BY FIELD(severity, %s, %s, %s, %s) DESC, issue_count DESC, attachment_id DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated read of live audit state.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder count is dynamic: $where comes from filter_clause() and $order adds four; the merged value list matches, asserted by AuditFiltersTest::test_every_filtered_query_binds_all_of_its_placeholders().
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is schema-controlled and escaped with esc_sql(); all values are bound.
				'SELECT * FROM `' . $table . '` ' . $where . $order . ' LIMIT %d OFFSET %d',
				array_merge(
					$base,
					array(
						Audit_Issue::SEVERITY_INFORMATION,
						Audit_Issue::SEVERITY_REVIEW,
						Audit_Issue::SEVERITY_WARNING,
						Audit_Issue::SEVERITY_CRITICAL,
						$per_page,
						$offset,
					)
				)
			),
			ARRAY_A
		);

		return array(
			'rows'        => is_array( $rows ) ? $rows : array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'filter'      => $filter,
		);
	}

	/**
	 * Row count for every filter, in a single pass over the table.
	 *
	 * One query rather than eight, so the filter chips cost the same as one count.
	 *
	 * @return array<string,int>
	 */
	public static function count_by_filter() {
		global $wpdb;

		$counts = array_fill_keys( Audit_Filters::slugs(), 0 );

		if ( ! self::table_exists() ) {
			return $counts;
		}

		$table  = esc_sql( self::table() );
		$select = array();
		$values = array();

		foreach ( Audit_Filters::slugs() as $slug ) {
			$clause = self::filter_clause( $slug );

			// Reuse each filter's own WHERE fragment as a SUM(CASE ...) condition so
			// the chips can never disagree with the table they link to.
			$condition = ltrim( $clause['sql'], ' ' );
			$condition = '' === $condition ? '1=1' : preg_replace( '/^AND\s+/', '', $condition );

			$select[] = 'SUM(CASE WHEN ' . $condition . ' THEN 1 ELSE 0 END) AS `' . esc_sql( $slug ) . '`';
			$values   = array_merge( $values, $clause['values'] );
		}

		$values[] = self::site_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate is cached by the caller.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The SELECT list is assembled from filter_clause() fragments, so the placeholder count is dynamic; $values is built in the same order and asserted by AuditFiltersTest::test_the_count_query_binds_every_placeholder_it_declares().
				'SELECT ' . implode( ', ', $select ) . ' FROM `' . $table . '` WHERE site_id = %s',
				$values
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return $counts;
		}

		foreach ( $counts as $slug => $unused ) {
			$counts[ $slug ] = isset( $row[ $slug ] ) ? (int) $row[ $slug ] : 0;
		}

		return $counts;
	}

	/**
	 * Drop every finding for this site. Used when starting a run from scratch.
	 *
	 * @return int Rows removed.
	 */
	public static function truncate_for_site() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table = esc_sql( self::table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deliberate reset of audit state.
		$removed = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is schema-controlled and escaped with esc_sql(); all values are bound.
				'DELETE FROM `' . $table . '` WHERE site_id = %s',
				self::site_id()
			)
		);

		return is_numeric( $removed ) ? (int) $removed : 0;
	}
}
