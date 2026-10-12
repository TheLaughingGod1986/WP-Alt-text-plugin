<?php
/**
 * Minimal $wpdb double for audit tests.
 *
 * Implements just enough of the wpdb surface for the audit queries: a prepare()
 * that really substitutes placeholders (so tests can assert on the SQL that
 * would be sent), plus scripted return values.
 */

declare(strict_types=1);

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared

class BBAI_Fake_WPDB {

	public $prefix   = 'wptest_';
	public $posts    = 'wptest_posts';
	public $postmeta = 'wptest_postmeta';

	/** @var array<int,string> Every query that reached the database. */
	public $queries = array();

	/** @var array<int,array<string,mixed>> Recorded delete() calls. */
	public $deletes = array();

	/** @var array<int,array<string,mixed>> Recorded update() calls. */
	public $updates = array();

	/** @var array<int,array<string,mixed>> Recorded insert() calls. */
	public $inserts = array();

	/** @var mixed Next update() return value; false mimics a failed write. */
	public $update_result = 1;

	/** @var mixed Next get_var() return value. */
	public $var_result = 0;

	/** @var array Next get_results() return value. */
	public $results_result = array();

	/** @var array|null Next get_row() return value. */
	public $row_result = null;

	/** @var mixed Next query() return value. */
	public $query_result = 1;

	/** @var array<int,array> Queue of get_results() responses, consumed in order. */
	public $results_queue = array();

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
	}

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * Substitute placeholders the way wpdb::prepare does for our usage.
	 *
	 * Accepts either a variadic argument list or a single array of values,
	 * matching the two calling conventions the audit code uses.
	 */
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$out    = '';
		$index  = 0;
		$length = strlen( $query );

		for ( $i = 0; $i < $length; $i++ ) {
			if ( '%' !== $query[ $i ] || $i + 1 >= $length ) {
				$out .= $query[ $i ];
				continue;
			}

			$type = $query[ $i + 1 ];

			if ( '%' === $type ) {
				$out .= '%';
				++$i;
				continue;
			}

			if ( ! in_array( $type, array( 'd', 's', 'f' ), true ) ) {
				$out .= $query[ $i ];
				continue;
			}

			$value = array_key_exists( $index, $args ) ? $args[ $index ] : null;
			++$index;
			++$i;

			if ( 'd' === $type ) {
				$out .= (string) (int) $value;
			} elseif ( 'f' === $type ) {
				$out .= (string) (float) $value;
			} elseif ( null === $value ) {
				$out .= 'NULL';
			} else {
				$out .= "'" . addslashes( (string) $value ) . "'";
			}
		}

		return $out;
	}

	public function get_var( $query = null ) {
		$this->queries[] = (string) $query;

		return $this->var_result;
	}

	public function get_results( $query = null, $output = OBJECT ) {
		$this->queries[] = (string) $query;

		if ( ! empty( $this->results_queue ) ) {
			return array_shift( $this->results_queue );
		}

		return $this->results_result;
	}

	public function get_row( $query = null, $output = OBJECT ) {
		$this->queries[] = (string) $query;

		return $this->row_result;
	}

	public function query( $query ) {
		$this->queries[] = (string) $query;

		return $this->query_result;
	}

	public function delete( $table, $where, $formats = null ) {
		$this->deletes[] = array(
			'table' => $table,
			'where' => $where,
		);

		return 1;
	}

	public function update( $table, $data, $where, $data_formats = null, $where_formats = null ) {
		$this->updates[] = array(
			'table' => $table,
			'data'  => $data,
			'where' => $where,
		);

		return $this->update_result;
	}

	public function insert( $table, $data, $formats = null ) {
		$this->inserts[] = array(
			'table' => $table,
			'data'  => $data,
		);

		return 1;
	}

	/** The most recent query issued. */
	public function last_query() {
		return empty( $this->queries ) ? '' : $this->queries[ count( $this->queries ) - 1 ];
	}

	/** Reset recorded state between tests. */
	public function reset() {
		$this->queries       = array();
		$this->deletes       = array();
		$this->updates       = array();
		$this->inserts       = array();
		$this->results_queue = array();
	}
}
