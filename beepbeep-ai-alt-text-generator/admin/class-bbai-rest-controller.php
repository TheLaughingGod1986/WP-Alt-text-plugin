<?php
/**
 * REST controller for Alt Text AI endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BbAI_REST_Controller {

	/**
	 * Core plugin implementation.
	 *
	 * @var BbAI_Core
	 */
	private $core;

	/**
	 * Constructor.
	 *
	 * @param BbAI_Core $core Core implementation instance.
	 */
	public function __construct( BbAI_Core $core ) {
		$this->core = $core;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'bbai/v1',
			'/generate/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_generate_single' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/alt/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_alt' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/list',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/stats',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_stats' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/usage',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_usage' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/plans',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_plans' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/queue',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_queue' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/logs',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_logs' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/logs/clear',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_logs_clear' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);
	}

	/**
	 * Permission callback shared across routes.
	 *
	 * @return bool
	 */
	public function can_edit_media() {
		if ( method_exists( $this->core, 'user_can_manage' ) && $this->core->user_can_manage() ) {
			return true;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Fetch debug logs via REST.
	 */
	public function handle_logs( \WP_REST_Request $request ) {
		if ( ! class_exists( 'BbAI_Debug_Log' ) ) {
			return rest_ensure_response([
				'logs' => [],
				'pagination' => [
					'page' => 1,
					'per_page' => 10,
					'total_pages' => 1,
					'total_items' => 0,
				],
				'stats' => [
					'total' => 0,
					'warnings' => 0,
					'errors' => 0,
					'last_api' => null,
				],
			]);
		}

		$level_raw = $request->get_param( 'level' );
		$search_raw = $request->get_param( 'search' );
		$date_raw = $request->get_param( 'date' );
		$per_page_raw = $request->get_param( 'per_page' );
		$page_raw = $request->get_param( 'page' );

		$args = [
			'level'    => is_string( $level_raw ) ? sanitize_text_field( $level_raw ) : '',
			'search'   => is_string( $search_raw ) ? sanitize_text_field( $search_raw ) : '',
			'date'     => is_string( $date_raw ) ? sanitize_text_field( $date_raw ) : '',
			'per_page' => absint( $per_page_raw ?: 10 ),
			'page'     => absint( $page_raw ?: 1 ),
		];

		return rest_ensure_response( BbAI_Debug_Log::get_logs( $args ) );
	}

	/**
	 * Clear logs via REST.
	 */
	public function handle_logs_clear( \WP_REST_Request $request ) {
		if ( ! class_exists( 'BbAI_Debug_Log' ) ) {
			return rest_ensure_response([
				'cleared' => false,
				'stats' => []
			]);
		}

		$older_than_raw = $request->get_param( 'older_than' );
		$older_than = absint( $older_than_raw );
		if ( $older_than > 0 ) {
			BbAI_Debug_Log::delete_older_than( $older_than );
		} else {
			BbAI_Debug_Log::clear_logs();
		}

		return rest_ensure_response([
			'cleared' => true,
			'stats'   => BbAI_Debug_Log::get_stats(),
		]);
	}

	/**
	 * Generate ALT text for a single attachment.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_generate_single( \WP_REST_Request $request ) {
		// Suppress any HTML output that might break JSON response
		$output_started = ob_get_level() > 0;
		if ( ! $output_started ) {
			ob_start();
		}
		
		try {
			$id_raw = $request->get_param( 'id' );
			$id = absint( $id_raw );
			
			if ( $id <= 0 ) {
				if ( ! $output_started ) {
					ob_end_clean();
				}
				return new \WP_Error( 'invalid_attachment', 'Invalid attachment ID.', [ 'status' => 400 ] );
			}
			
			$alt = $this->core->generate_and_save( $id, 'ajax' );

			if ( is_wp_error( $alt ) ) {
				$error_code = $alt->get_error_code();
				$error_message = $alt->get_error_message();
				
				// Return proper REST error response
				if ( 'bbai_dry_run' === $error_code ) {
					// Try to get stats, but don't fail if it errors
					try {
						$stats = $this->core->get_media_stats();
					} catch ( \Exception $e ) {
						$stats = [];
					}
					
					if ( ! $output_started ) {
						ob_end_clean();
					}
					
					return rest_ensure_response([
						'id'      => $id,
						'code'    => $error_code,
						'message' => $error_message,
						'prompt'  => $alt->get_error_data()['prompt'] ?? '',
						'stats'   => $stats,
					]);
				}
				
				// Convert WP_Error to REST error response
				$status = 500;
				if ( $error_code === 'limit_reached' ) {
					$status = 403;
				} elseif ( $error_code === 'auth_required' || $error_code === 'user_not_found' ) {
					$status = 401;
				} elseif ( $error_code === 'not_image' || $error_code === 'invalid_attachment' ) {
					$status = 400;
				}
				
				if ( ! $output_started ) {
					ob_end_clean();
				}
				
				return new \WP_Error( $error_code, $error_message, [ 'status' => $status ] );
			}

			// Try to get meta and stats, but don't fail if they error
			try {
				$meta = $this->core->prepare_attachment_snapshot( $id );
			} catch ( \Exception $e ) {
				$meta = [];
			}
			
			try {
				$stats = $this->core->get_media_stats();
			} catch ( \Exception $e ) {
				$stats = [];
			}
			
			if ( ! $output_started ) {
				ob_end_clean();
			}
			
			return rest_ensure_response([
				'id'   => $id,
				'alt'  => $alt,
				'meta' => $meta,
				'stats'=> $stats,
			]);
		} catch ( \Exception $e ) {
			if ( ! $output_started ) {
				ob_end_clean();
			}
			// Catch any PHP exceptions and return proper JSON error
			return new \WP_Error( 
				'generation_failed', 
				'Failed to generate alt text: ' . $e->getMessage(), 
				[ 'status' => 500 ] 
			);
		} catch ( \Error $e ) {
			if ( ! $output_started ) {
				ob_end_clean();
			}
			// Also catch PHP 7+ Error objects (non-Exception errors)
			return new \WP_Error( 
				'generation_failed', 
				'Failed to generate alt text: ' . $e->getMessage(), 
				[ 'status' => 500 ] 
			);
		}
	}

	/**
	 * Persist manual ALT text adjustments.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_save_alt( \WP_REST_Request $request ) {
		$id_raw = $request->get_param( 'id' );
		$id = absint( $id_raw );
		$alt_raw = $request->get_param( 'alt' );
		$alt = is_string( $alt_raw ) ? sanitize_text_field( trim( $alt_raw ) ) : '';

		if ( $id <= 0 ) {
			return new \WP_Error( 'invalid_attachment', 'Invalid attachment ID.', [ 'status' => 400 ] );
		}

		if ( '' === $alt ) {
			return new \WP_Error( 'invalid_alt', __( 'ALT text cannot be empty.', 'beepbeep-ai-alt-text-generator' ), [ 'status' => 400 ] );
		}

		$alt_sanitized = wp_strip_all_tags( $alt );

		$usage = [
			'prompt'     => 0,
			'completion' => 0,
			'total'      => 0,
		];

		$post      = get_post( $id );
		$file_path = get_attached_file( $id );
		$context   = [
			'filename'   => $file_path ? basename( $file_path ) : '',
			'title'      => get_the_title( $id ),
			'caption'    => $post->post_excerpt ?? '',
			'post_title' => '',
		];

		if ( $post && $post->post_parent ) {
			$parent = get_post( $post->post_parent );
			if ( $parent ) {
				$context['post_title'] = $parent->post_title;
			}
		}

		$review_result = null;
		$api_client    = $this->core->get_api_client();

		if ( $api_client ) {
			$review_response = $api_client->review_alt_text( $id, $alt_sanitized, $context );
			if ( ! is_wp_error( $review_response ) && ! empty( $review_response['review'] ) ) {
				$review      = $review_response['review'];
				$issues      = [];
				$issue_items = $review['issues'] ?? [];
				if ( ! empty( $issue_items ) && is_array( $issue_items ) ) {
					foreach ( $issue_items as $issue ) {
						if ( is_string( $issue ) && '' !== $issue ) {
							$issues[] = sanitize_text_field( $issue );
						}
					}
				}

				$review_usage = [
					'prompt'     => intval( $review['usage']['prompt_tokens'] ?? 0 ),
					'completion' => intval( $review['usage']['completion_tokens'] ?? 0 ),
					'total'      => intval( $review['usage']['total_tokens'] ?? 0 ),
				];

				$review_result = [
					'score'   => intval( $review['score'] ?? 0 ),
					'status'  => sanitize_key( $review['status'] ?? '' ),
					'grade'   => sanitize_text_field( $review['grade'] ?? '' ),
					'summary' => isset( $review['summary'] ) ? sanitize_text_field( $review['summary'] ) : '',
					'issues'  => $issues,
					'model'   => sanitize_text_field( $review['model'] ?? '' ),
					'usage'   => $review_usage,
				];
			}
		}

		$this->core->persist_generation_result(
			$id,
			$alt_sanitized,
			$usage,
			'manual-edit',
			'manual-input',
			'manual',
			$review_result
		);

		return [
			'id'     => $id,
			'alt'    => $alt_sanitized,
			'meta'   => $this->core->prepare_attachment_snapshot( $id ),
			'stats'  => $this->core->get_media_stats(),
			'source' => 'manual-edit',
		];
	}

	/**
	 * Provide attachment IDs for queues (missing/all).
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_list( \WP_REST_Request $request ) {
		$scope_raw = $request->get_param( 'scope' );
		$scope = ( is_string( $scope_raw ) && 'all' === sanitize_key( $scope_raw ) ) ? 'all' : 'missing';
		$limit_raw = $request->get_param( 'limit' );
		$limit = max( 1, min( 500, absint( $limit_raw ?: 100 ) ) );

		$ids = ( 'missing' === $scope )
			? $this->core->get_missing_attachment_ids( $limit )
			: $this->core->get_all_attachment_ids( $limit, 0 );

		return [
			'ids' => array_map( 'intval', $ids ),
		];
	}

	/**
	 * Return media library stats with optional cache invalidation.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_stats( \WP_REST_Request $request ) {
		$fresh_raw = $request->get_param( 'fresh' );
		$fresh = filter_var( $fresh_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( $fresh === true ) {
			$this->core->invalidate_stats_cache();
		}

		return $this->core->get_media_stats();
	}

	/**
	 * Return usage metrics from backend.
	 *
	 * @return array|\WP_Error
	 */
	public function handle_usage() {
		$api_client = $this->core->get_api_client();
		if ( ! $api_client ) {
			return new \WP_Error( 'missing_client', 'API client not available.' );
		}

		$usage = $api_client->get_usage();
		if ( is_wp_error( $usage ) ) {
			return $usage;
		}

		return $usage;
	}

	/**
	 * Expose checkout plans/prices.
	 *
	 * @return array
	 */
	public function handle_plans() {
		return [
			'prices' => $this->core->get_checkout_price_ids(),
		];
	}

	/**
	 * Return queue status snapshot.
	 *
	 * @return array
	 */
	public function handle_queue() {
		$stats    = BbAI_Queue::get_stats();
		$recent   = BbAI_Queue::get_recent( apply_filters( 'bbai_queue_recent_limit', 10 ) );
		$failures = BbAI_Queue::get_recent_failures( apply_filters( 'bbai_queue_fail_limit', 5 ) );

		return [
			'stats'    => $stats,
			'recent'   => array_map( [ $this, 'sanitize_job_row' ], $recent ),
			'failures' => array_map( [ $this, 'sanitize_job_row' ], $failures ),
		];
	}

	/**
	 * Normalize queue job payloads for REST responses.
	 *
	 * @param array $row Raw queue row.
	 * @return array
	 */
	private function sanitize_job_row( array $row ) {
		$attachment_id = intval( $row['attachment_id'] ?? 0 );

		return [
			'id'               => intval( $row['id'] ?? 0 ),
			'attachment_id'    => $attachment_id,
			'status'           => sanitize_key( $row['status'] ?? '' ),
			'attempts'         => intval( $row['attempts'] ?? 0 ),
			'source'           => sanitize_key( $row['source'] ?? '' ),
			'last_error'       => isset( $row['last_error'] ) ? wp_kses_post( $row['last_error'] ) : '',
			'enqueued_at'      => $row['enqueued_at'] ?? '',
			'locked_at'        => $row['locked_at'] ?? '',
			'completed_at'     => $row['completed_at'] ?? '',
			'attachment_title' => get_the_title( $attachment_id ),
			'edit_url'         => esc_url_raw( add_query_arg( 'item', $attachment_id, admin_url( 'upload.php' ) ) ),
		];
	}
}
