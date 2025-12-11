<?php
/**
 * REST controller for Alt Text AI endpoints.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Optti\Framework\Queue;
use BeepBeepAI\AltTextGenerator\Debug_Log;

/**
 * REST API controller for Alt Text AI plugin endpoints.
 *
 * Handles all REST API routes for the plugin including generation, usage tracking,
 * queue management, and license operations.
 *
 * @package BeepBeepAI\AltTextGenerator
 * @since   4.2.3
 */
class REST_Controller {

	/**
	 * Core plugin implementation.
	 *
	 * @var \BeepBeepAI\AltTextGenerator\Core
	 */
	private $core;

	/**
	 * Constructor.
	 *
	 * @param \BeepBeepAI\AltTextGenerator\Core $core Core implementation instance.
	 */
	public function __construct( \BeepBeepAI\AltTextGenerator\Core $core ) {
		$this->core = $core;
	}

	/**
	 * Register all REST API routes for the plugin.
	 *
	 * @since 4.2.3
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'bbai/v1',
			'/generate/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_generate_single' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/alt/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_alt' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_list' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_stats' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_usage' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);
		// Fallback to catch legacy requests that append query params directly to rest_route (e.g., rest_route=/bbai/v1/usage?force_refresh=true).
		register_rest_route(
			'bbai/v1',
			'/usage(?P<extra>.*)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_usage' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/plans',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_plans' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/usage/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_usage_summary' ),
				'permission_callback' => array( $this, 'can_view_usage' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/usage/by-user',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_usage_by_user' ),
				'permission_callback' => array( $this, 'can_view_team_usage' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/usage/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_usage_events' ),
				'permission_callback' => array( $this, 'can_view_team_usage' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_queue' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_logs' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/logs/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_logs_clear' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/user-usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_user_usage' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_events' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_log_event' ),
				'permission_callback' => array( $this, 'can_edit_media' ),
			)
		);

		register_rest_route(
			'bbai/v1',
			'/license/attach',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_license_attach' ),
				'permission_callback' => array( $this, 'can_manage_license' ),
			)
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
	 * Permission callback for license management routes.
	 *
	 * @return bool
	 */
	public function can_manage_license() {
		if ( method_exists( $this->core, 'user_can_manage' ) && $this->core->user_can_manage() ) {
			return true;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Fetch debug logs via REST.
	 */
	public function handle_logs( \WP_REST_Request $request ) {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			return rest_ensure_response(
				array(
					'logs'       => array(),
					'pagination' => array(
						'page'        => 1,
						'per_page'    => 10,
						'total_pages' => 1,
						'total_items' => 0,
					),
					'stats'      => array(
						'total'    => 0,
						'warnings' => 0,
						'errors'   => 0,
						'last_api' => null,
					),
				)
			);
		}

		$level_raw    = $request->get_param( 'level' );
		$search_raw   = $request->get_param( 'search' );
		$date_raw     = $request->get_param( 'date' );
		$per_page_raw = $request->get_param( 'per_page' );
		$page_raw     = $request->get_param( 'page' );

		$args = array(
			'level'    => is_string( $level_raw ) ? sanitize_text_field( $level_raw ) : '',
			'search'   => is_string( $search_raw ) ? sanitize_text_field( $search_raw ) : '',
			'date'     => is_string( $date_raw ) ? sanitize_text_field( $date_raw ) : '',
			'per_page' => absint( $per_page_raw ?: 10 ),
			'page'     => absint( $page_raw ?: 1 ),
		);

		return rest_ensure_response( Debug_Log::get_logs( $args ) );
	}

	/**
	 * Clear logs via REST.
	 */
	public function handle_logs_clear( \WP_REST_Request $request ) {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			return rest_ensure_response(
				array(
					'cleared' => false,
					'stats'   => array(),
				)
			);
		}

		$older_than_raw = $request->get_param( 'older_than' );
		$older_than     = absint( $older_than_raw );
		if ( $older_than > 0 ) {
			Debug_Log::delete_older_than( $older_than );
		} else {
			Debug_Log::clear_logs();
		}

		return rest_ensure_response(
			array(
				'cleared' => true,
				'stats'   => Debug_Log::get_stats(),
			)
		);
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
			$id     = absint( $id_raw );

			if ( $id <= 0 ) {
				if ( ! $output_started ) {
					ob_end_clean();
				}
				return new \WP_Error( 'invalid_attachment', 'Invalid attachment ID.', array( 'status' => 400 ) );
			}

			$alt = $this->core->generate_and_save( $id, 'ajax' );

			if ( is_wp_error( $alt ) ) {
				$error_code    = $alt->get_error_code();
				$error_message = $alt->get_error_message();

				// Return proper REST error response
				if ( 'bbai_dry_run' === $error_code ) {
					// Try to get stats, but don't fail if it errors
					try {
						$stats = $this->core->get_media_stats();
					} catch ( \Exception $e ) {
						$stats = array();
					}

					if ( ! $output_started ) {
						ob_end_clean();
					}

					return rest_ensure_response(
						array(
							'id'      => $id,
							'code'    => $error_code,
							'message' => $error_message,
							'prompt'  => $alt->get_error_data()['prompt'] ?? '',
							'stats'   => $stats,
						)
					);
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

				return new \WP_Error( $error_code, $error_message, array( 'status' => $status ) );
			}

			// Try to get meta and stats, but don't fail if they error
			try {
				$meta = $this->core->prepare_attachment_snapshot( $id );
			} catch ( \Exception $e ) {
				$meta = array();
			}

			try {
				$stats = $this->core->get_media_stats();
			} catch ( \Exception $e ) {
				$stats = array();
			}

			if ( ! $output_started ) {
				ob_end_clean();
			}

			return rest_ensure_response(
				array(
					'id'    => $id,
					'alt'   => $alt,
					'meta'  => $meta,
					'stats' => $stats,
				)
			);
		} catch ( \Exception $e ) {
			if ( ! $output_started ) {
				ob_end_clean();
			}
			// Catch any PHP exceptions and return proper JSON error
			return new \WP_Error(
				'generation_failed',
				'Failed to generate alt text: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		} catch ( \Error $e ) {
			if ( ! $output_started ) {
				ob_end_clean();
			}
			// Also catch PHP 7+ Error objects (non-Exception errors)
			return new \WP_Error(
				'generation_failed',
				'Failed to generate alt text: ' . $e->getMessage(),
				array( 'status' => 500 )
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
		$id_raw  = $request->get_param( 'id' );
		$id      = absint( $id_raw );
		$alt_raw = $request->get_param( 'alt' );
		$alt     = is_string( $alt_raw ) ? sanitize_text_field( trim( $alt_raw ) ) : '';

		if ( $id <= 0 ) {
			return new \WP_Error( 'invalid_attachment', 'Invalid attachment ID.', array( 'status' => 400 ) );
		}

		if ( '' === $alt ) {
			return new \WP_Error( 'invalid_alt', __( 'ALT text cannot be empty.', 'beepbeep-ai-alt-text-generator' ), array( 'status' => 400 ) );
		}

		$alt_sanitized = wp_strip_all_tags( $alt );

		$usage = array(
			'prompt'     => 0,
			'completion' => 0,
			'total'      => 0,
		);

		$post      = get_post( $id );
		$file_path = get_attached_file( $id );
		$context   = array(
			'filename'   => $file_path ? basename( $file_path ) : '',
			'title'      => get_the_title( $id ),
			'caption'    => $post->post_excerpt ?? '',
			'post_title' => '',
		);

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
				$issues      = array();
				$issue_items = $review['issues'] ?? array();
				if ( ! empty( $issue_items ) && is_array( $issue_items ) ) {
					foreach ( $issue_items as $issue ) {
						if ( is_string( $issue ) && '' !== $issue ) {
							$issues[] = sanitize_text_field( $issue );
						}
					}
				}

				$review_usage = array(
					'prompt'     => intval( $review['usage']['prompt_tokens'] ?? 0 ),
					'completion' => intval( $review['usage']['completion_tokens'] ?? 0 ),
					'total'      => intval( $review['usage']['total_tokens'] ?? 0 ),
				);

				$review_result = array(
					'score'   => intval( $review['score'] ?? 0 ),
					'status'  => sanitize_key( $review['status'] ?? '' ),
					'grade'   => sanitize_text_field( $review['grade'] ?? '' ),
					'summary' => isset( $review['summary'] ) ? sanitize_text_field( $review['summary'] ) : '',
					'issues'  => $issues,
					'model'   => sanitize_text_field( $review['model'] ?? '' ),
					'usage'   => $review_usage,
				);
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

		return array(
			'id'     => $id,
			'alt'    => $alt_sanitized,
			'meta'   => $this->core->prepare_attachment_snapshot( $id ),
			'stats'  => $this->core->get_media_stats(),
			'source' => 'manual-edit',
		);
	}

	/**
	 * Provide attachment IDs for queues (missing/all).
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_list( \WP_REST_Request $request ) {
		$scope_raw = $request->get_param( 'scope' );
		$scope     = ( is_string( $scope_raw ) && 'all' === sanitize_key( $scope_raw ) ) ? 'all' : 'missing';
		$limit_raw = $request->get_param( 'limit' );
		$limit     = max( 1, min( 500, absint( $limit_raw ?: 100 ) ) );

		$ids = ( 'missing' === $scope )
			? $this->core->get_missing_attachment_ids( $limit )
			: $this->core->get_all_attachment_ids( $limit, 0 );

		return array(
			'ids' => array_map( 'intval', $ids ),
		);
	}

	/**
	 * Return media library stats with optional cache invalidation.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_stats( \WP_REST_Request $request ) {
		$fresh_raw = $request->get_param( 'fresh' );
		$fresh     = filter_var( $fresh_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
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
	public function handle_usage( \WP_REST_Request $request ) {
		$api_client = $this->core->get_api_client();
		if ( ! $api_client ) {
			return new \WP_Error( 'missing_client', 'API client not available.' );
		}

		// Check if force refresh is requested
		$force_refresh = $request->get_param( 'force_refresh' ) === 'true' || $request->get_param( 'refresh' ) === 'true';
		if ( ! $force_refresh && $request->get_param( 'extra' ) ) {
			$extra = (string) $request->get_param( 'extra' );
			if ( strpos( $extra, 'force_refresh=true' ) !== false || strpos( $extra, 'refresh=true' ) !== false ) {
				$force_refresh = true;
			}
		}
		
		if ( $force_refresh ) {
			// Clear cache before fetching fresh data
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
			
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
			\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
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
		return array(
			'prices' => $this->core->get_checkout_price_ids(),
		);
	}

	/**
	 * Return queue status snapshot.
	 *
	 * @return array
	 */
	public function handle_queue() {
		// Ensure Queue is initialized.
		Queue::init( 'bbai' );

		$stats    = Queue::get_stats();
		$recent   = Queue::get_recent( apply_filters( 'bbai_queue_recent_limit', 10 ) );
		$failures = Queue::get_recent_failures( apply_filters( 'bbai_queue_fail_limit', 5 ) );

		return array(
			'stats'    => $stats,
			'recent'   => array_map( array( $this, 'sanitize_job_row' ), $recent ),
			'failures' => array_map( array( $this, 'sanitize_job_row' ), $failures ),
		);
	}

	/**
	 * Normalize queue job payloads for REST responses.
	 *
	 * @param array $row Raw queue row.
	 * @return array
	 */
	private function sanitize_job_row( array $row ) {
		$attachment_id = intval( $row['entity_id'] ?? 0 );

		return array(
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
		);
	}

	/**
	 * Get user usage data for multi-user visualization.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_user_usage( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';

		// Use token quota service for accurate site-wide quota
		$quota = \BeepBeepAI\AltTextGenerator\Token_Quota_Service::get_site_quota();
		if ( is_wp_error( $quota ) ) {
			// Fallback to usage tracker if quota service fails
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$usage_tracker = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display();
			$total_allowed = $usage_tracker['limit'] ?? 50;
			$total_used    = \BeepBeepAI\AltTextGenerator\Usage\get_monthly_total_usage();
		} else {
			$total_allowed = $quota['limit'] ?? 50;
			$total_used    = $quota['used'] ?? 0;
		}

		$users = \BeepBeepAI\AltTextGenerator\Usage\get_monthly_usage_by_user();

		// Check if current user is admin
		$is_admin        = current_user_can( 'manage_options' );
		$show_full_names = $is_admin || get_option( 'bbai_show_full_user_names', false );

		// Anonymize usernames for non-admins if needed
		if ( ! $show_full_names ) {
			foreach ( $users as &$user ) {
				if ( $user['user_id'] > 0 && $user['user_id'] !== get_current_user_id() ) {
					// Show only first letter + last 3 chars of username
					$username = $user['username'];
					if ( strlen( $username ) > 4 ) {
						$user['username'] = substr( $username, 0, 1 ) . '***' . substr( $username, -3 );
					} else {
						$user['username'] = substr( $username, 0, 1 ) . '***';
					}
					$user['display_name'] = substr( $user['display_name'], 0, 1 ) . '***';
				}
			}
		}

		return array(
			'total_used'    => $total_used,
			'total_allowed' => $total_allowed,
			'users'         => $users,
		);
	}

	/**
	 * Get usage events with filters.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_events( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';

		$filters = array(
			'user_id'     => $request->get_param( 'user_id' ),
			'date_from'   => $request->get_param( 'date_from' ),
			'date_to'     => $request->get_param( 'date_to' ),
			'action_type' => $request->get_param( 'action_type' ),
			'per_page'    => $request->get_param( 'per_page' ) ?: 50,
			'page'        => $request->get_param( 'page' ) ?: 1,
		);

		$result = \BeepBeepAI\AltTextGenerator\Usage\get_usage_events( $filters );

		// Check if current user is admin
		$is_admin        = current_user_can( 'manage_options' );
		$show_full_names = $is_admin || get_option( 'bbai_show_full_user_names', false );

		// Anonymize usernames for non-admins if needed
		if ( ! $show_full_names ) {
			foreach ( $result['events'] as &$event ) {
				if ( $event['user_id'] > 0 && $event['user_id'] !== get_current_user_id() ) {
					$username = $event['username'];
					if ( strlen( $username ) > 4 ) {
						$event['username'] = substr( $username, 0, 1 ) . '***' . substr( $username, -3 );
					} else {
						$event['username'] = substr( $username, 0, 1 ) . '***';
					}
					$event['display_name'] = substr( $event['display_name'], 0, 1 ) . '***';
				}
			}
		}

		return $result;
	}

	/**
	 * Log a usage event (internal use).
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_log_event( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';

		$user_id     = get_current_user_id();
		$tokens_used = absint( $request->get_param( 'tokens_used' ) ?: 1 );
		$action_type = sanitize_key( $request->get_param( 'action_type' ) ?: 'generate' );
		$context     = array(
			'image_id' => $request->get_param( 'image_id' ) ? absint( $request->get_param( 'image_id' ) ) : null,
			'post_id'  => $request->get_param( 'post_id' ) ? absint( $request->get_param( 'post_id' ) ) : null,
		);

		$result = \BeepBeepAI\AltTextGenerator\Usage\record_usage_event( $user_id, $tokens_used, $action_type, $context );

		if ( $result === false ) {
			return new \WP_Error( 'log_failed', 'Failed to log usage event.', array( 'status' => 500 ) );
		}

		return array(
			'success' => true,
			'id'      => $result,
		);
	}

	/**
	 * Get usage summary (site-wide quota)
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_usage_summary( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
		$api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();

		$usage = $api_client->get_usage();
		if ( is_wp_error( $usage ) ) {
			return $usage;
		}

		$credits_used      = intval( $usage['credits_used'] ?? $usage['used'] ?? 0 );
		$credits_remaining = intval( $usage['credits_remaining'] ?? $usage['remaining'] ?? 0 );
		$total_limit       = $credits_used + $credits_remaining;

		return array(
			'total_limit' => $total_limit,
			'total_used'  => $credits_used,
			'remaining'   => $credits_remaining,
			'period_start'=> $usage['period_start'] ?? '',
			'period_end'  => $usage['period_end'] ?? '',
			'plan_type'   => $usage['plan_type'] ?? '',
		);
	}

	/**
	 * Get usage breakdown by user
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_usage_by_user( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
		$api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();

		// Include user headers for per-user breakdown
		$usage = $api_client->get_usage();
		if ( is_wp_error( $usage ) ) {
			return $usage;
		}

		$users = array();
		if ( ! empty( $usage['users'] ) && is_array( $usage['users'] ) ) {
			foreach ( $usage['users'] as $user ) {
				$users[] = array(
					'user_id'      => intval( $user['user_id'] ?? 0 ),
					'display_name' => $user['display_name'] ?? $user['username'] ?? __( 'System', 'beepbeep-ai-alt-text-generator' ),
					'username'     => $user['username'] ?? '',
					'credits_used' => intval( $user['credits_used'] ?? $user['used'] ?? 0 ),
					'last_used'    => $user['last_used'] ?? null,
				);
			}
		}

		return $users;
	}

	/**
	 * Get usage events with filters
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_usage_events( \WP_REST_Request $request ) {
		// Backend usage API does not expose event logs here; return empty structure to keep UI stable.
		return array(
			'total'  => 0,
			'pages'  => 0,
			'page'   => 1,
			'events' => array(),
		);
	}

	/**
	 * Permission check: Can view usage (any logged-in user with plugin access)
	 *
	 * @return bool
	 */
	public function can_view_usage() {
		return is_user_logged_in() && current_user_can( 'upload_files' );
	}

	/**
	 * Permission check: Can view team usage (admins only)
	 *
	 * @return bool
	 */
	public function can_view_team_usage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'bbai_view_team_usage' );
	}

	/**
	 * Auto-attach license after checkout/signup.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_license_attach( \WP_REST_Request $request ) {
		if ( ! method_exists( $this->core, 'get_api_client' ) ) {
			return new \WP_Error( 'bbai_missing_client', __( 'API client unavailable', 'beepbeep-ai-alt-text-generator' ) );
		}

		$api_client = $this->core->get_api_client();
		if ( ! $api_client ) {
			return new \WP_Error( 'bbai_missing_client', __( 'API client unavailable', 'beepbeep-ai-alt-text-generator' ) );
		}

		$args = array(
			'siteUrl'   => $request->get_param( 'siteUrl' ),
			'siteHash'  => $request->get_param( 'siteHash' ),
			'installId' => $request->get_param( 'installId' ),
		);

		$result = $api_client->auto_attach_license( $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => isset( $result['message'] ) ? $result['message'] : __( 'License attached successfully', 'beepbeep-ai-alt-text-generator' ),
				'license' => isset( $result['license'] ) ? $result['license'] : array(),
				'site'    => isset( $result['site'] ) ? $result['site'] : array(),
			)
		);
	}
}
