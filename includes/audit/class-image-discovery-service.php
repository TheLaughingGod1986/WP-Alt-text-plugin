<?php
/**
 * Finds the images an audit run needs to look at.
 *
 * Deliberately query-frugal: one keyset-paginated query per batch plus one
 * usage lookup, and no per-image queries at all. Display concerns such as
 * thumbnail URLs are resolved separately, for the handful of rows actually on
 * screen, rather than for every image in the library.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

namespace BeepBeepAI\AltTextGenerator\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attachment discovery for the image audit.
 */
class Image_Discovery_Service {

	const DEFAULT_BATCH_SIZE = 100;
	const MIN_BATCH_SIZE     = 10;
	const MAX_BATCH_SIZE     = 250;

	/**
	 * Longest alt value considered for duplicate grouping, and the cap on how
	 * many duplicate groups we hold in memory for one run.
	 */
	const DUPLICATE_MIN_LENGTH = 15;
	const DUPLICATE_MAX_GROUPS = 5000;

	const META_ALT        = '_wp_attachment_image_alt';
	const META_DECORATIVE = '_bbai_decorative';
	const META_FILE       = '_wp_attached_file';
	const META_THUMBNAIL  = '_thumbnail_id';

	/**
	 * Resolve the batch size, clamped to a sane range.
	 *
	 * @param int|null $requested Caller preference.
	 * @return int
	 */
	public static function batch_size( $requested = null ) {
		$size = null === $requested ? self::DEFAULT_BATCH_SIZE : (int) $requested;

		/**
		 * Filter the number of images processed per audit batch.
		 *
		 * Larger batches finish sooner but hold the request open for longer.
		 *
		 * @since 4.8.0
		 *
		 * @param int $size Requested batch size.
		 */
		$size = (int) apply_filters( 'bbai_audit_batch_size', $size );

		return max( self::MIN_BATCH_SIZE, min( self::MAX_BATCH_SIZE, $size ) );
	}

	/**
	 * How many images are in scope.
	 *
	 * @return int
	 */
	public function count_images() {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit scans must read live data.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers come from trusted core $wpdb.
				'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
				'attachment',
				'inherit',
				$image_mime_like
			)
		);
	}

	/**
	 * Fetch one batch of images as audit contexts.
	 *
	 * Uses keyset pagination (`ID < cursor`, newest first) rather than OFFSET, so
	 * cost stays flat however deep the run gets and concurrent uploads cannot
	 * shift rows into or out of an already-processed window.
	 *
	 * @param int   $after_attachment_id Cursor; 0 starts from the newest image.
	 * @param int   $size                Batch size.
	 * @param array $duplicate_map       Normalised-alt hash => group size.
	 * @return Image_Audit_Context[]
	 */
	public function get_batch( $after_attachment_id, $size, array $duplicate_map = array() ) {
		$rows = $this->get_batch_rows( (int) $after_attachment_id, (int) $size );

		if ( empty( $rows ) ) {
			return array();
		}

		$ids = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['ID'];
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		$usage = $this->resolve_usage( $ids );

		$contexts = array();
		foreach ( $rows as $row ) {
			$contexts[] = $this->build_context( $row, $usage, $duplicate_map );
		}

		return $contexts;
	}

	/**
	 * Raw batch rows.
	 *
	 * One grouped LEFT JOIN pulls alt text, the decorative flag and the file path
	 * together. MAX(CASE ...) yields NULL when a meta row is absent and '' when it
	 * exists but is empty — the distinction the missing/empty rules depend on.
	 *
	 * @param int $after_attachment_id Cursor.
	 * @param int $size                Batch size.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_batch_rows( $after_attachment_id, $size ) {
		global $wpdb;

		$size                = self::batch_size( $size );
		$after_attachment_id = max( 0, (int) $after_attachment_id );
		$image_mime_like     = $wpdb->esc_like( 'image/' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit scans must read live data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers come from trusted core $wpdb.
				'SELECT p.ID,
						p.post_title,
						p.post_excerpt,
						p.post_content,
						p.post_parent,
						p.post_date,
						p.post_mime_type,
						MAX(CASE WHEN m.meta_key = %s THEN m.meta_value ELSE NULL END) AS alt_text,
						MAX(CASE WHEN m.meta_key = %s THEN m.meta_value ELSE NULL END) AS decorative,
						MAX(CASE WHEN m.meta_key = %s THEN m.meta_value ELSE NULL END) AS attached_file
				   FROM ' . $wpdb->posts . ' p
				   LEFT JOIN ' . $wpdb->postmeta . ' m
					 ON p.ID = m.post_id
					AND m.meta_key IN (%s, %s, %s)
				  WHERE p.post_type = %s
					AND p.post_status = %s
					AND p.post_mime_type LIKE %s
					AND (%d = 0 OR p.ID < %d)
				  GROUP BY p.ID
				  ORDER BY p.ID DESC
				  LIMIT %d',
				self::META_ALT,
				self::META_DECORATIVE,
				self::META_FILE,
				self::META_ALT,
				self::META_DECORATIVE,
				self::META_FILE,
				'attachment',
				'inherit',
				$image_mime_like,
				$after_attachment_id,
				$after_attachment_id,
				$size
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Work out, cheaply, where a batch of images is used.
	 *
	 * Phase one uses two signals only: the attachment's parent post, and whether
	 * anything uses it as a featured image. There is no rendered-HTML crawl, so
	 * an image used only inside block or shortcode markup reads as unattached.
	 * The issue copy says as much.
	 *
	 * @param int[] $ids Attachment IDs.
	 * @return array{known:bool,featured:array<int,bool>}
	 */
	public function resolve_usage( array $ids ) {
		/**
		 * Filter whether the audit resolves usage context at all.
		 *
		 * Returning false makes every image report "usage not determined" rather
		 * than guessing, which is the honest degraded mode for very large sites.
		 *
		 * @since 4.8.0
		 *
		 * @param bool  $resolve Whether to resolve usage.
		 * @param int[] $ids     Attachment IDs in this batch.
		 */
		if ( ! apply_filters( 'bbai_audit_resolve_usage_context', true, $ids ) ) {
			return array(
				'known'    => false,
				'featured' => array(),
			);
		}

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return array(
				'known'    => true,
				'featured' => array(),
			);
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( self::META_THUMBNAIL ), $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit scans must read live data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $placeholders is a generated run of %d tokens; every id is bound through prepare().
				'SELECT DISTINCT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s AND meta_value IN (' . $placeholders . ')',
				$params
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			// A failed lookup must not masquerade as "definitely unused".
			return array(
				'known'    => false,
				'featured' => array(),
			);
		}

		$featured = array();
		foreach ( $rows as $row ) {
			$featured[ (int) $row['meta_value'] ] = true;
		}

		return array(
			'known'    => true,
			'featured' => $featured,
		);
	}

	/**
	 * Turn a database row into an audit context.
	 *
	 * Kept separate from the query so the mapping — including the null/empty alt
	 * distinction — can be unit tested without a database.
	 *
	 * @param array<string,mixed> $row           Raw row.
	 * @param array               $usage         Result of resolve_usage().
	 * @param array               $duplicate_map Normalised-alt hash => group size.
	 * @return Image_Audit_Context
	 */
	public function build_context( array $row, array $usage = array(), array $duplicate_map = array() ) {
		$attachment_id = isset( $row['ID'] ) ? (int) $row['ID'] : 0;
		$parent_id     = isset( $row['post_parent'] ) ? (int) $row['post_parent'] : 0;

		$usage_known = ! isset( $usage['known'] ) || ! empty( $usage['known'] );
		$featured    = isset( $usage['featured'] ) && is_array( $usage['featured'] ) ? $usage['featured'] : array();
		$is_featured = isset( $featured[ $attachment_id ] );
		$has_usage   = $parent_id > 0 || $is_featured;

		// A NULL alt column means no meta row exists; '' means it exists and is empty.
		$alt = array_key_exists( 'alt_text', $row ) && null !== $row['alt_text']
			? (string) $row['alt_text']
			: null;

		$duplicate_group_size = 0;
		if ( null !== $alt && '' !== trim( $alt ) ) {
			$hash = sha1( Image_Audit_Context::normalise( $alt ) );
			if ( isset( $duplicate_map[ $hash ] ) ) {
				$duplicate_group_size = (int) $duplicate_map[ $hash ];
			}
		}

		return new Image_Audit_Context(
			array(
				'attachment_id'        => $attachment_id,
				'alt'                  => $alt,
				'filename'             => $this->filename_stem( isset( $row['attached_file'] ) ? (string) $row['attached_file'] : '' ),
				'title'                => isset( $row['post_title'] ) ? (string) $row['post_title'] : '',
				'caption'              => isset( $row['post_excerpt'] ) ? (string) $row['post_excerpt'] : '',
				'description'          => isset( $row['post_content'] ) ? (string) $row['post_content'] : '',
				'parent_post_id'       => $parent_id,
				'usage_context_known'  => $usage_known,
				'has_usage'            => $has_usage,
				'context_summary'      => $this->context_summary( $parent_id, $is_featured, $usage_known ),
				'is_decorative'        => ! empty( $row['decorative'] ),
				'duplicate_group_size' => $duplicate_group_size,
				'mime_type'            => isset( $row['post_mime_type'] ) ? (string) $row['post_mime_type'] : '',
				'uploaded_at'          => isset( $row['post_date'] ) ? (string) $row['post_date'] : '',
			)
		);
	}

	/**
	 * Build the run-scoped duplicate map.
	 *
	 * One bounded query per run, normalised in PHP, so per-image duplicate lookup
	 * is an array read rather than a query. Values shorter than the floor are
	 * excluded up front: "Acme logo" repeated in a footer is not a finding.
	 *
	 * @return array{map:array<string,int>,truncated:bool}
	 */
	public function build_duplicate_map() {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit scans must read live data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers come from trusted core $wpdb.
				'SELECT alt.meta_value AS alt_text, COUNT(*) AS cnt
				   FROM ' . $wpdb->posts . ' p
				   INNER JOIN ' . $wpdb->postmeta . ' alt
					 ON p.ID = alt.post_id
					AND alt.meta_key = %s
				  WHERE p.post_type = %s
					AND p.post_status = %s
					AND p.post_mime_type LIKE %s
					AND CHAR_LENGTH(TRIM(alt.meta_value)) >= %d
				  GROUP BY alt.meta_value
				 HAVING cnt > 1
				  ORDER BY cnt DESC
				  LIMIT %d',
				self::META_ALT,
				'attachment',
				'inherit',
				$image_mime_like,
				self::DUPLICATE_MIN_LENGTH,
				self::DUPLICATE_MAX_GROUPS
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array(
				'map'       => array(),
				'truncated' => false,
			);
		}

		/*
		 * SQL groups on the raw value; normalising in PHP then merges groups that
		 * differ only by case or punctuation, which is what the duplicate rule
		 * compares. Known limitation: two values that each appear exactly once but
		 * normalise alike are dropped by HAVING cnt > 1 before PHP sees them, so
		 * that pair is not reported. Erring towards under-reporting is the right
		 * side to be wrong on for a rule that asks the user to go and look.
		 */
		$map = array();
		foreach ( $rows as $row ) {
			$normalised = Image_Audit_Context::normalise( (string) $row['alt_text'] );
			if ( '' === $normalised ) {
				continue;
			}

			$hash  = sha1( $normalised );
			$count = (int) $row['cnt'];

			$map[ $hash ] = isset( $map[ $hash ] ) ? $map[ $hash ] + $count : $count;
		}

		return array(
			'map'       => $map,
			'truncated' => count( $rows ) >= self::DUPLICATE_MAX_GROUPS,
		);
	}

	/**
	 * Display data for a small set of rows.
	 *
	 * Called only for the images actually shown in the issues table, never for a
	 * whole library — this is where the per-attachment WordPress calls belong.
	 *
	 * @param int[] $ids Attachment IDs.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_display_data( array $ids ) {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true );
		}

		$data = array();

		foreach ( $ids as $id ) {
			$thumbnail = '';
			if ( function_exists( 'wp_get_attachment_image_src' ) ) {
				$src = wp_get_attachment_image_src( $id, 'thumbnail' );
				if ( is_array( $src ) && ! empty( $src[0] ) ) {
					$thumbnail = (string) $src[0];
				}
			}

			$data[ $id ] = array(
				'attachment_id' => $id,
				'title'         => get_the_title( $id ),
				'url'           => (string) wp_get_attachment_url( $id ),
				'thumbnail_url' => $thumbnail,
				'edit_link'     => (string) get_edit_post_link( $id, 'raw' ),
				'alt'           => (string) get_post_meta( $id, self::META_ALT, true ),
			);
		}

		return $data;
	}

	/**
	 * File name without directory or extension.
	 *
	 * @param string $attached_file Value of _wp_attached_file.
	 * @return string
	 */
	private function filename_stem( $attached_file ) {
		if ( '' === $attached_file ) {
			return '';
		}

		$base = basename( $attached_file );
		$dot  = strrpos( $base, '.' );

		return false === $dot ? $base : substr( $base, 0, $dot );
	}

	/**
	 * Short, human-readable usage summary.
	 *
	 * @param int  $parent_id   Parent post ID.
	 * @param bool $is_featured Whether the image is a featured image.
	 * @param bool $usage_known Whether usage was resolved at all.
	 * @return string
	 */
	private function context_summary( $parent_id, $is_featured, $usage_known ) {
		if ( ! $usage_known ) {
			return __( 'Usage not determined', 'beepbeep-ai-alt-text-generator' );
		}

		if ( $is_featured ) {
			return __( 'Used as a featured image', 'beepbeep-ai-alt-text-generator' );
		}

		if ( $parent_id > 0 ) {
			$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $parent_id ) : '';
			$title = trim( $title );

			if ( '' !== $title ) {
				return self::truncate(
					sprintf(
						/* translators: %s: title of the post the image is attached to. */
						__( 'Attached to: %s', 'beepbeep-ai-alt-text-generator' ),
						$title
					)
				);
			}

			return __( 'Attached to a post', 'beepbeep-ai-alt-text-generator' );
		}

		return __( 'Not attached to a post', 'beepbeep-ai-alt-text-generator' );
	}

	/**
	 * Keep a summary inside the column width.
	 *
	 * @param string $value Summary.
	 * @return string
	 */
	private static function truncate( $value ) {
		$limit = 191;

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value ) > $limit ? mb_substr( $value, 0, $limit - 1 ) . '…' : $value;
		}

		return strlen( $value ) > $limit ? substr( $value, 0, $limit - 1 ) . '…' : $value;
	}
}
