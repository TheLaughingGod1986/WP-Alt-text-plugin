<?php
/**
 * REST controller for Alt Text AI endpoints.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Debug_Log;
use BeepBeepAI\AltTextGenerator\Input_Validator;

class REST_Controller {

	private const DASHBOARD_BOOTSTRAP_SYNC_LOCK_TTL = 5 * MINUTE_IN_SECONDS;
	private const DASHBOARD_BOOTSTRAP_SYNC_FAILURE_TTL = 15 * MINUTE_IN_SECONDS;
	private const DASHBOARD_BOOTSTRAP_SYNC_SUCCESS_TTL = 2 * HOUR_IN_SECONDS;

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
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'bbai/v1',
			'/generate/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_generate_single' ],
				'permission_callback' => [ $this, 'can_edit_attachment' ],
				'args'                => [
					'id'         => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_positive_int_arg' ],
					],
					'regenerate' => [
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => [ __CLASS__, 'sanitize_bool_arg' ],
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/alt/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_alt' ],
				'permission_callback' => [ $this, 'can_edit_attachment' ],
				'args'                => [
					'id'  => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_positive_int_arg' ],
					],
					'alt' => [
						'required'          => true,
						'sanitize_callback' => [ __CLASS__, 'sanitize_text_arg' ],
						'validate_callback' => [ __CLASS__, 'validate_non_empty_text_arg' ],
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/attachment-alt/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_read_attachment_alt' ],
				'permission_callback' => [ $this, 'can_edit_attachment' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_positive_int_arg' ],
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/review/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_mark_reviewed' ],
				'permission_callback' => [ $this, 'can_edit_attachment' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_positive_int_arg' ],
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/review',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_mark_reviewed_batch' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/approve-all-alt-text',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_approve_all_alt_text' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/alt/clear',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_clear_alt_batch' ],
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
				'args'                => [
					'scope' => [
						'required'          => false,
						'default'           => 'missing',
						'sanitize_callback' => [ __CLASS__, 'sanitize_scope_arg' ],
					],
					'limit' => [
						'required'          => false,
						'default'           => 100,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'required'          => false,
						'default'           => 100,
						'sanitize_callback' => 'absint',
					],
						'page' => [
							'required'          => false,
							'default'           => 1,
							'sanitize_callback' => 'absint',
						],
						'include_preview' => [
							'required'          => false,
							'default'           => false,
							'sanitize_callback' => [ __CLASS__, 'sanitize_bool_arg' ],
						],
						'preview_limit' => [
							'required'          => false,
							'default'           => 5,
							'sanitize_callback' => 'absint',
						],
					],
				]
			);

		register_rest_route(
			'bbai/v1',
			'/stats',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_stats' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
				'args'                => [
					'fresh' => [
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => [ __CLASS__, 'sanitize_bool_arg' ],
					],
				],
			]
		);

		// Logged-in dashboard state endpoint.
		// Returns a single resolved DashboardState object for authenticated users.
		// This is the polling target used by the logged-in dashboard controller.
		register_rest_route(
			'bbai/v1',
			'/dashboard',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_logged_in_dashboard' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/dashboard/state-truth',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_logged_in_dashboard_state_truth' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/dashboard/bootstrap-sync',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_logged_in_dashboard_bootstrap_sync' ],
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
			'/usage/summary',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_usage_summary' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/usage/by-user',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_usage_by_user' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/usage/events',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_usage_events' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
				'args'                => [
					'user_id'     => [
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
					'from'        => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_date_arg' ],
					],
					'to'          => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_date_arg' ],
					],
					'action_type' => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_action_type_arg' ],
					],
					'per_page'    => [
						'required'          => false,
						'default'           => 50,
						'sanitize_callback' => 'absint',
					],
					'page'        => [
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/queue',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_queue' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/logs',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_logs' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
				'args'                => [
					'level'     => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_log_level_arg' ],
					],
					'search'    => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_text_arg' ],
					],
					'date'      => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_date_arg' ],
					],
					'date_from' => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_date_arg' ],
					],
					'date_to'   => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_date_arg' ],
					],
					'per_page'  => [
						'required'          => false,
						'default'           => 10,
						'sanitize_callback' => 'absint',
					],
					'page'      => [
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/logs/clear',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_logs_clear' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
				'args'                => [
					'older_than' => [
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/user-usage',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_user_usage' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/events',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_events' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
				'args'                => [
					'user_id'     => [
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
					'date_from'   => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_date_arg' ],
					],
					'date_to'     => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_date_arg' ],
					],
					'action_type' => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => [ __CLASS__, 'sanitize_action_type_arg' ],
					],
					'per_page'    => [
						'required'          => false,
						'default'           => 50,
						'sanitize_callback' => 'absint',
					],
					'page'        => [
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/log',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_log_event' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
				'args'                => [
					'tokens_used' => [
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_positive_int_arg' ],
					],
					'action_type' => [
						'required'          => false,
						'default'           => 'generate',
						'sanitize_callback' => [ __CLASS__, 'sanitize_action_type_arg' ],
					],
					'image_id'    => [
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_non_negative_int_arg' ],
					],
					'post_id'     => [
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_non_negative_int_arg' ],
					],
				],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/trial-status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_trial_status' ],
				'permission_callback' => [ $this, 'can_edit_media' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/assistant/chat',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_assistant_chat' ],
				'permission_callback' => [ $this, 'can_manage_admin' ],
			]
		);

		register_rest_route(
			'bbai/v1',
			'/improve-alt/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_improve_alt' ],
				'permission_callback' => [ $this, 'can_edit_attachment' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_positive_int_arg' ],
					],
				],
			]
		);
	}

	/**
	 * Permission callback: can edit a specific attachment.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return bool
	 */
	public function can_edit_attachment( \WP_REST_Request $request ) {
		$id = absint( $request['id'] ?? 0 );
		if ( $id <= 0 ) {
			return false;
		}

		return current_user_can( 'edit_post', $id );
	}

	/**
	 * Permission callback shared across media routes.
	 * When a license key is active, allows any user who can upload files to use shared credits
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 *
	 * @return bool
	 */
	public function can_edit_media( \WP_REST_Request $request ) {
		// If license is active, allow any user who can upload files (site-wide licensing)
		$api_client = null;
		if ( method_exists( $this->core, 'get_api_client' ) ) {
			$api_client = $this->core->get_api_client();
		}
		if ( $api_client && $api_client->has_active_license() ) {
			return is_user_logged_in() && current_user_can( 'upload_files' );
		}
		
		// Without license, require manage capability
		if ( method_exists( $this->core, 'user_can_manage' ) && $this->core->user_can_manage() ) {
			return true;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback: admin-only routes.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return bool
	 */
	public function can_manage_admin( \WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Debug Logs visibility policy for support tooling.
	 *
	 * @return bool
	 */
	private function can_view_debug_logs() {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || current_user_can( 'manage_options' );
	}

	/**
	 * Sanitize boolean-like REST values.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_bool_arg( $value ) {
		return rest_sanitize_boolean( $value );
	}

	/**
	 * Sanitize text REST values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_text_arg( $value ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Validate non-empty text values.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function validate_non_empty_text_arg( $value ) {
		return is_string( $value ) && trim( $value ) !== '';
	}

	/**
	 * Validate positive integer REST values.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function validate_positive_int_arg( $value ) {
		return absint( $value ) > 0;
	}

	/**
	 * Validate non-negative integer REST values.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function validate_non_negative_int_arg( $value ) {
		return absint( $value ) >= 0;
	}

	/**
	 * Sanitize scope values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_scope_arg( $value ) {
		$scope = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
		return in_array( $scope, [ 'missing', 'all', 'needs-review' ], true ) ? $scope : 'missing';
	}

	/**
	 * Sanitize log level values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_log_level_arg( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$level = sanitize_key( (string) $value );
		if ( '' === $level || 'all' === $level ) {
			return '';
		}

		$aliases = [
			'warn'    => 'warning',
			'warning' => 'warning',
			'err'     => 'error',
			'fatal'   => 'error',
		];
		if ( isset( $aliases[ $level ] ) ) {
			$level = $aliases[ $level ];
		}

		return in_array( $level, [ 'debug', 'info', 'warning', 'error' ], true ) ? $level : '';
	}

	/**
	 * Sanitize usage action type values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_action_type_arg( $value ) {
		$action_type = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
		$allowed     = [ '', 'generate', 'regenerate', 'bulk', 'api', 'upload', 'inline', 'queue', 'manual', 'onboarding' ];
		return in_array( $action_type, $allowed, true ) ? $action_type : '';
	}

	/**
	 * Sanitize YYYY-MM-DD date values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_date_arg( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$date = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) === 1 ? $date : '';
	}

	/**
	 * Fetch debug logs via REST.
	 */
	public function handle_logs( \WP_REST_Request $request ) {
		if ( ! $this->can_view_debug_logs() ) {
			return new \WP_Error(
				'debug_logs_forbidden',
				__( 'Debug logs are not available for this account.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$level_input = $request->get_param( 'level' );
		$search_input = $request->get_param( 'search' );
		$date_input = $request->get_param( 'date' );
		$date_from_input = $request->get_param( 'date_from' );
		$date_to_input = $request->get_param( 'date_to' );
		$per_page_input = $request->get_param( 'per_page' );
		$page_input = $request->get_param( 'page' );

		$args = [
			'level'    => self::sanitize_log_level_arg( $level_input ),
			'search'   => is_string( $search_input ) ? sanitize_text_field( $search_input ) : '',
			'date'     => is_string( $date_input ) ? sanitize_text_field( $date_input ) : '',
			'date_from' => is_string( $date_from_input ) ? sanitize_text_field( $date_from_input ) : '',
			'date_to'   => is_string( $date_to_input ) ? sanitize_text_field( $date_to_input ) : '',
			'per_page' => absint( $per_page_input ?: 10 ),
			'page'     => absint( $page_input ?: 1 ),
		];

		return rest_ensure_response( $this->core->get_debug_payload( $args ) );
	}

	/**
	 * Clear logs via REST.
	 */
	public function handle_logs_clear( \WP_REST_Request $request ) {
		if ( ! $this->can_view_debug_logs() ) {
			return new \WP_Error(
				'debug_logs_forbidden',
				__( 'Debug logs are not available for this account.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			return rest_ensure_response([
				'cleared' => false,
				'stats' => []
			]);
		}

		$older_than_input = $request->get_param( 'older_than' );
		$older_than = absint( $older_than_input );
		if ( $older_than > 0 ) {
			Debug_Log::delete_older_than( $older_than );
		} else {
			Debug_Log::clear_logs();
		}

		return rest_ensure_response([
			'cleared' => true,
			'stats'   => Debug_Log::get_stats(),
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
			$id_input = $request->get_param( 'id' );
			$id = absint( $id_input );
			
			if ( $id <= 0 ) {
				if ( ! $output_started ) {
					ob_end_clean();
				}
				return new \WP_Error( 'invalid_attachment', 'Invalid attachment ID.', [ 'status' => 400 ] );
			}
			
			$regenerate = Input_Validator::bool_param( $request, 'regenerate' );
			$alt = $this->core->generate_and_save( $id, 'ajax', 0, [], $regenerate );

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
				if ( $error_code === 'limit_reached' || $error_code === 'bbai_trial_exhausted' ) {
					$status = 403;
				} elseif ( $error_code === 'auth_required' || $error_code === 'user_not_found' ) {
					$status = 401;
				} elseif ( $error_code === 'not_image' || $error_code === 'invalid_attachment' ) {
					$status = 400;
				}

				$error_response_data = [ 'status' => $status ];
				// Include trial exhaustion metadata so the UI can show the signup CTA.
				if ( $error_code === 'bbai_trial_exhausted' ) {
					$trial_data = $alt->get_error_data();
					if ( is_array( $trial_data ) ) {
						$error_response_data = array_merge( $error_response_data, $trial_data );
					}
				}

				if ( ! $output_started ) {
					ob_end_clean();
				}

				return new \WP_Error( $error_code, $error_message, $error_response_data );
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
	 * Read persisted ALT text for an attachment (post meta), for UI refresh fallbacks.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_read_attachment_alt( \WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( $id <= 0 ) {
			return new \WP_Error(
				'invalid_attachment',
				__( 'Invalid attachment ID.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 400 ]
			);
		}

		$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

		return rest_ensure_response(
			[
				'id'       => $id,
				'alt'      => $alt,
				'alt_text' => $alt,
				'altText'  => $alt,
			]
		);
	}

	/**
	 * Persist manual ALT text adjustments.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_save_alt( \WP_REST_Request $request ) {
		$id_input = $request->get_param( 'id' );
		$id = absint( $id_input );
		$alt_input = $request->get_param( 'alt' );
		$alt = is_string( $alt_input ) ? sanitize_text_field( trim( $alt_input ) ) : '';

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
			'stats'  => $this->core->get_dashboard_stats_payload( true ),
			'approved' => false,
			'approved_at' => '',
			'source' => 'manual-edit',
		];
	}

	/**
	 * Mark a single attachment as reviewed and approved by the user.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_mark_reviewed( \WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( $id <= 0 ) {
			return new \WP_Error( 'invalid_attachment', __( 'Invalid attachment ID.', 'beepbeep-ai-alt-text-generator' ), [ 'status' => 400 ] );
		}

		$result = $this->core->mark_attachment_reviewed( $id );
		if ( empty( $result['approved'] ) ) {
			return new \WP_Error( 'invalid_alt', __( 'ALT text must exist before it can be approved.', 'beepbeep-ai-alt-text-generator' ), [ 'status' => 400 ] );
		}

		if ( function_exists( 'bbai_telemetry_emit' ) ) {
			bbai_telemetry_emit(
				'alt_marked_reviewed',
				[
					'attachment_id' => $id,
					'scope'         => 'single',
				]
			);
		}

		return [
			'id' => $id,
			'alt' => $result['alt'] ?? '',
			'approved' => true,
			'approved_at' => $result['approved_at'] ?? '',
			'meta' => $this->core->prepare_attachment_snapshot( $id ),
			'stats' => $this->core->get_dashboard_stats_payload( true ),
		];
	}

	/**
	 * Mark multiple attachments as reviewed and approved by the user.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_mark_reviewed_batch( \WP_REST_Request $request ) {
		$ids_param = $request->get_param( 'ids' );
		$ids = is_array( $ids_param ) ? array_map( 'absint', $ids_param ) : [];
		$ids = array_values(
			array_filter(
				array_unique( $ids ),
				static function ( int $attachment_id ): bool {
					return $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id );
				}
			)
		);

		if ( empty( $ids ) ) {
			return new \WP_Error( 'invalid_ids', __( 'Select at least one image to mark as reviewed.', 'beepbeep-ai-alt-text-generator' ), [ 'status' => 400 ] );
		}

		$result = $this->core->mark_attachments_reviewed( $ids );

		$approved_count = (int) ( $result['approved_count'] ?? 0 );
		if ( $approved_count > 0 && function_exists( 'bbai_telemetry_emit' ) ) {
			bbai_telemetry_emit(
				'alt_marked_reviewed',
				[
					'approved_count' => $approved_count,
					'scope'          => 'batch',
				]
			);
		}

		return [
			'approved_ids' => $result['approved_ids'] ?? [],
			'approved_count' => (int) ( $result['approved_count'] ?? 0 ),
			'approved_at' => $result['approved_at'] ?? '',
			'stats' => $this->core->get_dashboard_stats_payload( true ),
		];
	}

	/**
	 * Approve every attachment currently waiting for review.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_approve_all_alt_text( \WP_REST_Request $request ) {
		$ids = [];
		if ( method_exists( $this->core, 'get_needs_review_attachment_ids' ) ) {
			$ids = $this->core->get_needs_review_attachment_ids( PHP_INT_MAX, 0 );
		}

		$ids = array_values(
			array_filter(
				array_unique( array_map( 'absint', (array) $ids ) ),
				static function ( int $attachment_id ): bool {
					return $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id );
				}
			)
		);

		$result = [
			'approved_ids'   => [],
			'approved_count' => 0,
			'approved_at'    => '',
		];

		if ( ! empty( $ids ) ) {
			$result = $this->core->mark_attachments_reviewed( $ids );
		}

		$approved_count = (int) ( $result['approved_count'] ?? 0 );
		if ( $approved_count > 0 && function_exists( 'bbai_telemetry_emit' ) ) {
			bbai_telemetry_emit(
				'alt_marked_reviewed',
				[
					'approved_count' => $approved_count,
					'scope'          => 'approve_all',
				]
			);
		}

		$stats = $this->core->get_dashboard_stats_payload( true );

		return [
			'approved_ids'    => $result['approved_ids'] ?? [],
			'approved_count'  => $approved_count,
			'approved_at'     => $result['approved_at'] ?? '',
			'stats'           => $stats,
			'dashboard_state' => $this->get_logged_in_dashboard_state_response_data( $request ),
		];
	}

	/**
	 * Meta keys to remove when clearing ALT (review snapshot + approval).
	 *
	 * @return array<int, string>
	 */
	private static function bbai_alt_dependent_meta_keys(): array {
		return [
			'_bbai_review_score',
			'_bbai_review_status',
			'_bbai_review_grade',
			'_bbai_review_summary',
			'_bbai_review_issues',
			'_bbai_review_model',
			'_bbai_reviewed_at',
			'_bbai_review_alt_hash',
			'_bbai_user_approved_hash',
			'_bbai_user_approved_at',
		];
	}

	/**
	 * Remove ALT text and related review meta for multiple attachments.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_clear_alt_batch( \WP_REST_Request $request ) {
		$ids_param = $request->get_param( 'ids' );
		$ids       = is_array( $ids_param ) ? array_map( 'absint', $ids_param ) : [];
		$ids       = array_values(
			array_filter(
				array_unique( $ids ),
				static function ( int $attachment_id ): bool {
					return $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id );
				}
			)
		);

		if ( empty( $ids ) ) {
			return new \WP_Error( 'invalid_ids', __( 'Select at least one image to clear ALT text.', 'beepbeep-ai-alt-text-generator' ), [ 'status' => 400 ] );
		}

		$cleared = [];
		foreach ( $ids as $id ) {
			delete_post_meta( $id, '_wp_attachment_image_alt' );
			foreach ( self::bbai_alt_dependent_meta_keys() as $meta_key ) {
				delete_post_meta( $id, $meta_key );
			}
			$cleared[] = $id;
		}

		return [
			'cleared_ids' => $cleared,
			'stats'       => $this->core->get_dashboard_stats_payload( true ),
		];
	}

	/**
	 * Provide attachment IDs for queues (missing/all).
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_list( \WP_REST_Request $request ) {
		$scope_input = $request->get_param( 'scope' );
		$scope       = is_string( $scope_input ) ? sanitize_key( $scope_input ) : 'missing';
		if ( ! in_array( $scope, [ 'missing', 'all', 'needs-review' ], true ) ) {
			$scope = 'missing';
		}
		$legacy_limit_input = $request->get_param( 'limit' );
		$per_page_input     = $request->get_param( 'per_page' );
		$page_input         = $request->get_param( 'page' );
		$has_per_page       = method_exists( $request, 'has_param' ) ? $request->has_param( 'per_page' ) : null !== $request->get_param( 'per_page' );

		$per_page = $has_per_page
			? absint( $per_page_input )
			: absint( $legacy_limit_input );
		if ( $per_page <= 0 ) {
			$per_page = 100;
		}
		$per_page = max( 1, min( 500, $per_page ) );
		$page     = max( 1, absint( $page_input ?: 1 ) );
		$offset   = ( $page - 1 ) * $per_page;
		$include_preview_raw = $request->get_param( 'include_preview' );
		$include_preview = filter_var( $include_preview_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$preview_limit = max( 1, min( 5, absint( $request->get_param( 'preview_limit' ) ?: 5 ) ) );
		$include_items_raw = $request->get_param( 'include_items' );
		$include_items = filter_var( $include_items_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		if ( 'all' === $scope ) {
			$ids = $this->core->get_all_attachment_ids( $per_page, $offset );
		} elseif ( 'needs-review' === $scope ) {
			$ids = $this->core->get_needs_review_attachment_ids( $per_page, $offset );
		} else {
			$ids = $this->core->get_missing_attachment_ids( $per_page, $offset );
		}

		$response = [
			'ids' => array_map( 'intval', $ids ),
			'pagination' => [
				'per_page' => $per_page,
				'page'     => $page,
			],
		];

		// include_items=true: return rich attachment rows for each ID in this page.
		// Used by MissingAltTable and ReviewQueue dashboard surfaces.
		if ( true === $include_items ) {
			$items = [];
			foreach ( $ids as $id ) {
				$id = absint( $id );
				if ( $id > 0 ) {
					$items[] = $this->build_attachment_preview_item( $id );
				}
			}
			$response['items'] = $items;
		}

		if ( true === $include_preview && 'missing' === $scope ) {
			$stats = $this->core->get_media_stats();
			$missing_count = 0;
			if ( is_array( $stats ) ) {
				if ( isset( $stats['missing'] ) ) {
					$missing_count = max( 0, intval( $stats['missing'] ) );
				} elseif ( isset( $stats['missing_alt'] ) ) {
					$missing_count = max( 0, intval( $stats['missing_alt'] ) );
				}
			}

			$response['missing_count']  = $missing_count;
			$response['missing_images'] = $this->get_missing_images_preview( $preview_limit );
		}

		return $response;
	}

	/**
	 * Build and cache small missing-image preview data for dashboard limit-state UI.
	 *
	 * @param int $limit Maximum number of preview records.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_missing_images_preview( int $limit ): array {
		$limit = max( 1, min( 5, $limit ) );
		$cache_key = sprintf( 'bbai_missing_preview_%d_%d', get_current_blog_id(), $limit );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = $this->core->get_missing_attachment_ids( $limit, 0 );
		$preview = [];
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				continue;
			}
			$preview[] = $this->build_attachment_preview_item( $id );
		}

		set_transient( $cache_key, $preview, 60 );
		return $preview;
	}

	/**
	 * Build a compact attachment preview row for dashboard UI.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array<string, mixed>
	 */
	private function build_attachment_preview_item( int $attachment_id ): array {
		$filename = '';
		$attached_file = get_attached_file( $attachment_id );
		if ( is_string( $attached_file ) && $attached_file !== '' ) {
			$filename = wp_basename( $attached_file );
		}
		if ( '' === $filename ) {
			$filename = get_the_title( $attachment_id );
		}

		$thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( ! is_string( $thumb_url ) || '' === $thumb_url ) {
			$thumb_url = '';
		}

		return [
			'id'       => $attachment_id,
			'filename' => sanitize_text_field( (string) $filename ),
			'thumb_url' => $thumb_url ? esc_url_raw( $thumb_url ) : null,
		];
	}

	/**
	 * Return media library stats with optional cache invalidation.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_stats( \WP_REST_Request $request ) {
		$fresh_input = $request->get_param( 'fresh' );
		$fresh = filter_var( $fresh_input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		return $this->core->get_dashboard_stats_payload( $fresh === true );
	}

	/**
	 * Return the fully-resolved logged-in dashboard state object.
	 *
	 * This is the single polling target for the logged-in dashboard controller.
	 * All hero, donut, pill, usage-bar, and surface rendering must come from this.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_logged_in_dashboard( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-logged-in-dashboard-resolver.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';

		$truth = $this->get_logged_in_dashboard_state_truth_payload();
		if ( is_array( $truth ) ) {
			return rest_ensure_response(
				\BeepBeepAI\AltTextGenerator\Services\Logged_In_Dashboard_Resolver::resolve_from_truth(
					$truth,
					$this->get_logged_in_dashboard_plan_context()
				)
			);
		}

		$ctx = \BeepBeepAI\AltTextGenerator\Services\Logged_In_Dashboard_Resolver::build_ctx(
			[],
			[],
			[],
			[ 'code' => 'NO_API_KEY', 'message' => __( 'API key not connected. Open settings to continue.', 'beepbeep-ai-alt-text-generator' ) ]
		);

		return rest_ensure_response(
			\BeepBeepAI\AltTextGenerator\Services\Logged_In_Dashboard_Resolver::resolve( $ctx )
		);
	}

	/**
	 * Return the canonical dashboard truth payload used by the logged-in UI.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_logged_in_dashboard_state_truth( \WP_REST_Request $request ) {
		$truth = $this->get_logged_in_dashboard_state_truth_payload();
		if ( ! is_array( $truth ) ) {
			return new \WP_Error(
				'dashboard_state_truth_unavailable',
				__( 'Dashboard state truth is currently unavailable.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 503 ]
			);
		}

		return rest_ensure_response( $truth );
	}

	/**
	 * Trigger a one-time backend bootstrap sync when dashboard truth is linked but
	 * still backed by an empty/unseeded image ledger.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_logged_in_dashboard_bootstrap_sync( \WP_REST_Request $request ) {
		$truth = $this->get_logged_in_dashboard_state_truth_payload();
		if ( ! is_array( $truth ) ) {
			return new \WP_Error(
				'dashboard_bootstrap_truth_unavailable',
				__( 'Dashboard truth is currently unavailable.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 503 ]
			);
		}

		$eligibility = $this->get_dashboard_bootstrap_sync_eligibility( $truth );
		if ( empty( $eligibility['eligible'] ) ) {
			return rest_ensure_response(
				[
					'triggered' => false,
					'skipped'   => true,
					'reason'    => (string) ( $eligibility['reason'] ?? 'not_eligible' ),
					'debug'     => [
						'eligibility' => [
							'eligible'    => false,
							'reason'      => (string) ( $eligibility['reason'] ?? 'not_eligible' ),
							'linked_site' => '' !== (string) ( $eligibility['site_hash'] ?? '' ),
							'site_hash'   => (string) ( $eligibility['site_hash'] ?? '' ),
						],
					],
					'truth'     => $truth,
				]
			);
		}

		$site_hash = (string) ( $eligibility['site_hash'] ?? '' );
		$status    = $this->get_dashboard_bootstrap_sync_status( $site_hash );
		$now       = time();

		if ( ! empty( $status['status'] ) && ! empty( $status['expires_at'] ) && (int) $status['expires_at'] > $now ) {
			return rest_ensure_response(
				[
					'triggered' => false,
					'skipped'   => true,
					'reason'    => (string) $status['status'],
					'debug'     => [
						'eligibility' => [
							'eligible'    => true,
							'reason'      => (string) ( $eligibility['reason'] ?? 'eligible' ),
							'linked_site' => '' !== $site_hash,
							'site_hash'   => $site_hash,
						],
						'guard'       => [
							'status'      => (string) ( $status['status'] ?? '' ),
							'expires_at'  => (int) ( $status['expires_at'] ?? 0 ),
							'attempted_at'=> (string) ( $status['attempted_at'] ?? '' ),
						],
					],
					'truth'     => $truth,
				]
			);
		}

		$api_client = $this->core->get_api_client();
		if ( ! $api_client || ! method_exists( $api_client, 'sync_media_inventory_chunk' ) ) {
			return new \WP_Error(
				'dashboard_bootstrap_sync_unavailable',
				__( 'Media inventory bootstrap sync is not available.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 501 ]
			);
		}

		if (
			! method_exists( $this->core, 'get_media_inventory_sync_total' )
			|| ! method_exists( $this->core, 'get_media_inventory_sync_items' )
		) {
			return new \WP_Error(
				'dashboard_bootstrap_sync_unavailable',
				__( 'Media inventory bootstrap sync is not available.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 501 ]
			);
		}

		$local_total = max( 0, (int) $this->core->get_media_inventory_sync_total() );
		if ( $local_total <= 0 ) {
			return rest_ensure_response(
				[
					'triggered'   => false,
					'skipped'     => true,
					'reason'      => 'no_local_media',
					'local_total' => 0,
					'debug'       => [
						'eligibility'     => [
							'eligible'    => true,
							'reason'      => (string) ( $eligibility['reason'] ?? 'eligible' ),
							'linked_site' => '' !== $site_hash,
							'site_hash'   => $site_hash,
						],
						'media_inventory' => [
							'local_total'  => 0,
							'sample_items' => [],
						],
					],
					'truth'       => $truth,
				]
			);
		}

		$chunk_size  = max( 25, min( 250, (int) apply_filters( 'bbai_dashboard_bootstrap_sync_chunk_size', 100 ) ) );
		$chunk_count = max( 1, (int) ceil( $local_total / $chunk_size ) );
		$sent_count  = 0;
		$chunk_index = 0;
		$offset      = 0;
		$inserted_count = 0;
		$updated_count  = 0;
		$changed_count  = 0;
		$unchanged_count = 0;
		$inventory_sample = [];
		$last_chunk_count = 0;
		$upstream_endpoints = [];
		$bootstrap_started_at = gmdate( 'c', $now );

		Debug_Log::log(
			'info',
			'Dashboard bootstrap sync starting',
			[
				'site_hash'   => $site_hash,
				'local_total' => $local_total,
				'chunk_size'  => $chunk_size,
				'chunk_count' => $chunk_count,
			],
			'api'
		);

		$this->set_dashboard_bootstrap_sync_status(
			$site_hash,
			[
				'status'     => 'in_progress',
				'expires_at' => $now + self::DASHBOARD_BOOTSTRAP_SYNC_LOCK_TTL,
				'attempted_at' => gmdate( 'c', $now ),
			],
			self::DASHBOARD_BOOTSTRAP_SYNC_LOCK_TTL
		);

		while ( $offset < $local_total ) {
			$items = $this->core->get_media_inventory_sync_items( $chunk_size, $offset );
			if ( empty( $items ) ) {
				Debug_Log::log(
					'warning',
					'Dashboard bootstrap sync returned an empty media page',
					[
						'site_hash' => $site_hash,
						'offset'    => $offset,
						'chunk_size' => $chunk_size,
					],
					'api'
				);
				break;
			}

			$chunk_index++;
			$is_last_chunk = ( $offset + count( $items ) ) >= $local_total;
			$last_chunk_count = count( $items );
			$sample_items  = array_map(
				static function ( array $item ): array {
					$image = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : [];

					return [
						'attachment_id' => (string) ( $item['attachment_id'] ?? $item['attachmentId'] ?? '' ),
						'current_state' => (string) ( $item['current_state'] ?? $item['currentState'] ?? '' ),
						'image_url'     => (string) ( $item['image_url'] ?? $item['imageUrl'] ?? $image['url'] ?? '' ),
					];
				},
				array_slice( $items, 0, 3 )
			);
			if ( empty( $inventory_sample ) ) {
				$inventory_sample = $sample_items;
			}

			Debug_Log::log(
				'info',
				'Dashboard bootstrap sync sending chunk',
				[
					'site_hash'     => $site_hash,
					'chunk_index'   => $chunk_index,
					'chunk_count'   => $chunk_count,
					'offset'        => $offset,
					'item_count'    => count( $items ),
					'is_last_chunk' => $is_last_chunk,
					'sample_items'  => $sample_items,
				],
				'api'
			);
			$response      = $api_client->sync_media_inventory_chunk(
				(array) $items,
				[
					'reason'        => 'dashboard_bootstrap',
					'scope'         => 'full_site',
					'total_items'   => $local_total,
					'chunk_index'   => $chunk_index,
					'chunk_count'   => $chunk_count,
					'offset'        => $offset,
					'is_last_chunk' => $is_last_chunk,
				]
			);

			if ( is_wp_error( $response ) ) {
				$this->set_dashboard_bootstrap_sync_status(
					$site_hash,
					[
						'status'     => 'failed',
						'expires_at' => $now + self::DASHBOARD_BOOTSTRAP_SYNC_FAILURE_TTL,
						'attempted_at' => gmdate( 'c', $now ),
					],
					self::DASHBOARD_BOOTSTRAP_SYNC_FAILURE_TTL
				);

				return new \WP_Error(
					'dashboard_bootstrap_sync_failed',
					$response->get_error_message(),
					[
						'status'   => 502,
						'upstream' => $response->get_error_data(),
					]
				);
			}

			$inserted_count += max( 0, (int) ( $response['inserted'] ?? 0 ) );
			$updated_count  += max( 0, (int) ( $response['updated'] ?? 0 ) );
			$changed_count  += max( 0, (int) ( $response['changed'] ?? 0 ) );
			$unchanged_count += max( 0, (int) ( $response['unchanged'] ?? 0 ) );
			if ( ! empty( $response['endpoint'] ) ) {
				$upstream_endpoints[] = (string) $response['endpoint'];
				$upstream_endpoints   = array_values( array_unique( $upstream_endpoints ) );
			}

			Debug_Log::log(
				'info',
				'Dashboard bootstrap sync chunk completed',
				[
					'site_hash'   => $site_hash,
					'chunk_index' => $chunk_index,
					'endpoint'    => (string) ( $response['endpoint'] ?? '' ),
					'inserted'    => max( 0, (int) ( $response['inserted'] ?? 0 ) ),
					'updated'     => max( 0, (int) ( $response['updated'] ?? 0 ) ),
					'changed'     => max( 0, (int) ( $response['changed'] ?? 0 ) ),
					'unchanged'   => max( 0, (int) ( $response['unchanged'] ?? 0 ) ),
					'coverage'    => isset( $response['coverage'] ) && is_array( $response['coverage'] )
						? (string) ( $response['coverage']['status'] ?? '' )
						: '',
				],
				'api'
			);

			$sent_count += count( $items );
			$offset     += count( $items );

			if ( count( $items ) < $chunk_size ) {
				break;
			}
		}

		$refreshed_truth = $this->get_logged_in_dashboard_state_truth_payload();
		$refreshed_payload = is_array( $refreshed_truth['data'] ?? null ) ? $refreshed_truth['data'] : ( is_array( $refreshed_truth ) ? $refreshed_truth : [] );
		$refreshed_counts = is_array( $refreshed_payload['counts'] ?? null ) ? $refreshed_payload['counts'] : [];
		$refreshed_sources = $refreshed_payload['resolution_sources'] ?? $refreshed_payload['resolutionSources'] ?? $refreshed_payload['sources'] ?? [];
		$refreshed_sources = is_array( $refreshed_sources ) ? $refreshed_sources : [];
		$refreshed_resolution = is_array( $refreshed_payload['resolution'] ?? null ) ? $refreshed_payload['resolution'] : [];
		$refreshed_counts_source = (string) (
			$refreshed_sources['counts']
			?? $refreshed_sources['count_source']
			?? $refreshed_sources['countSource']
			?? $refreshed_resolution['count_source']
			?? $refreshed_resolution['countSource']
			?? ''
		);
		$refreshed_missing = max( 0, (int) ( $refreshed_counts['missing'] ?? $refreshed_counts['missing_alt'] ?? $refreshed_counts['missingAlt'] ?? 0 ) );
		$refreshed_review = max( 0, (int) ( $refreshed_counts['review'] ?? $refreshed_counts['needs_review'] ?? $refreshed_counts['needsReview'] ?? $refreshed_counts['to_review'] ?? $refreshed_counts['toReview'] ?? $refreshed_counts['weak'] ?? 0 ) );
		$refresh_eligibility = is_array( $refreshed_truth ) ? $this->get_dashboard_bootstrap_sync_eligibility( $refreshed_truth ) : [ 'eligible' => false ];

		$this->set_dashboard_bootstrap_sync_status(
			$site_hash,
			[
				'status'     => 'success',
				'expires_at' => $now + self::DASHBOARD_BOOTSTRAP_SYNC_SUCCESS_TTL,
				'attempted_at' => gmdate( 'c', $now ),
			],
			self::DASHBOARD_BOOTSTRAP_SYNC_SUCCESS_TTL
		);

		Debug_Log::log(
			'info',
			'Dashboard bootstrap sync finished',
			[
				'site_hash'      => $site_hash,
				'sent_count'     => $sent_count,
				'local_total'    => $local_total,
				'chunks'         => $chunk_index,
				'inserted'       => $inserted_count,
				'updated'        => $updated_count,
				'changed'        => $changed_count,
				'unchanged'      => $unchanged_count,
				'truth_state'    => is_array( $refreshed_truth ) ? (string) ( $refreshed_truth['state'] ?? '' ) : '',
				'count_source'   => is_array( $refreshed_truth['resolution_sources'] ?? null )
					? (string) ( $refreshed_truth['resolution_sources']['counts'] ?? '' )
					: '',
			],
			'api'
		);

		return rest_ensure_response(
			[
				'triggered'   => true,
				'sent_count'  => $sent_count,
				'local_total' => $local_total,
				'chunks'      => $chunk_index,
				'inserted'    => $inserted_count,
				'updated'     => $updated_count,
				'changed'     => $changed_count,
				'unchanged'   => $unchanged_count,
				'debug'       => [
					'eligibility'     => [
						'eligible'    => true,
						'reason'      => (string) ( $eligibility['reason'] ?? 'eligible' ),
						'linked_site' => '' !== $site_hash,
						'site_hash'   => $site_hash,
					],
					'media_inventory' => [
						'local_total'  => $local_total,
						'sample_items' => $inventory_sample,
					],
					'payload'         => [
						'request_image_count' => $last_chunk_count,
						'site_identifier'     => $site_hash,
					],
					'network'         => [
						'request_started_at' => $bootstrap_started_at,
						'upstream_endpoints' => $upstream_endpoints,
					],
					'response'        => [
						'success'   => true,
						'inserted'  => $inserted_count,
						'updated'   => $updated_count,
						'changed'   => $changed_count,
						'unchanged' => $unchanged_count,
					],
					'truth_refresh'   => [
						'state'                    => is_array( $refreshed_truth ) ? (string) ( $refreshed_truth['state'] ?? '' ) : '',
						'counts_source'            => $refreshed_counts_source,
						'missing'                  => $refreshed_missing,
						'review'                   => $refreshed_review,
						'still_bootstrap_eligible' => ! empty( $refresh_eligibility['eligible'] ),
					],
				],
				'truth'       => is_array( $refreshed_truth ) ? $refreshed_truth : null,
			]
		);
	}

	/**
	 * Determine whether the current dashboard truth should trigger an inventory
	 * bootstrap sync.
	 *
	 * @param array<string,mixed> $truth Raw dashboard truth payload.
	 * @return array<string,mixed>
	 */
	private function get_dashboard_bootstrap_sync_eligibility( array $truth ): array {
		$payload = isset( $truth['data'] ) && is_array( $truth['data'] ) ? $truth['data'] : $truth;
		if ( ! is_array( $payload ) ) {
			return [
				'eligible' => false,
				'reason'   => 'invalid_truth',
			];
		}

		if ( ! empty( $payload['fallback'] ) ) {
			return [
				'eligible' => false,
				'reason'   => 'fallback_truth',
			];
		}

		$site = is_array( $payload['site'] ?? null ) ? $payload['site'] : [];
		$site_hash = sanitize_text_field(
			(string) ( $site['site_hash'] ?? $site['siteHash'] ?? $site['site_id'] ?? $site['siteId'] ?? '' )
		);
		if ( '' === $site_hash ) {
			return [
				'eligible' => false,
				'reason'   => 'missing_site_hash',
			];
		}

		$state = strtoupper( trim( (string) ( $payload['state'] ?? '' ) ) );
		$counts = is_array( $payload['counts'] ?? null ) ? $payload['counts'] : [];
		$missing = max( 0, (int) ( $counts['missing'] ?? $counts['missing_alt'] ?? $counts['missingAlt'] ?? 0 ) );
		$review = max( 0, (int) ( $counts['review'] ?? $counts['needs_review'] ?? $counts['needsReview'] ?? $counts['weak'] ?? 0 ) );
		$complete = max( 0, (int) ( $counts['complete'] ?? $counts['optimized'] ?? $counts['optimised'] ?? 0 ) );
		$failed = max( 0, (int) ( $counts['failed'] ?? 0 ) );
		$total = max( 0, (int) ( $counts['total'] ?? $counts['total_images'] ?? $counts['totalImages'] ?? 0 ) );
		$zero_counts = 0 === $missing && 0 === $review && 0 === $complete && 0 === $failed && 0 === $total;

		$sources = $payload['resolution_sources'] ?? $payload['resolutionSources'] ?? $payload['sources'] ?? [];
		$sources = is_array( $sources ) ? $sources : [];
		$counts_source = strtolower(
			trim( (string) ( $sources['counts'] ?? $sources['count_source'] ?? $sources['countSource'] ?? '' ) )
		);
		$source_indicates_unseeded = '' !== $counts_source
			&& 1 === preg_match( '/assumed|empty|unseeded|seed|bootstrap|ledger/', $counts_source );

		return [
			'eligible'  => $source_indicates_unseeded || ( 'MISSING_ALT' === $state && $zero_counts ),
			'reason'    => $source_indicates_unseeded ? 'unseeded_counts_source' : ( $zero_counts ? 'missing_alt_zero_counts' : 'not_eligible' ),
			'site_hash' => $site_hash,
		];
	}

	/**
	 * Read the current bootstrap sync status for a linked site.
	 *
	 * @param string $site_hash Linked site identifier.
	 * @return array<string,mixed>
	 */
	private function get_dashboard_bootstrap_sync_status( string $site_hash ): array {
		if ( '' === $site_hash ) {
			return [];
		}

		$status = get_transient( $this->get_dashboard_bootstrap_sync_status_key( $site_hash ) );
		return is_array( $status ) ? $status : [];
	}

	/**
	 * Persist the bootstrap sync status for a linked site.
	 *
	 * @param string               $site_hash Linked site identifier.
	 * @param array<string,mixed> $status Status payload.
	 * @param int                  $ttl Seconds to retain the status.
	 * @return void
	 */
	private function set_dashboard_bootstrap_sync_status( string $site_hash, array $status, int $ttl ): void {
		if ( '' === $site_hash ) {
			return;
		}

		set_transient(
			$this->get_dashboard_bootstrap_sync_status_key( $site_hash ),
			$status,
			max( 1, $ttl )
		);
	}

	/**
	 * Build the transient key used to guard dashboard bootstrap sync attempts.
	 *
	 * @param string $site_hash Linked site identifier.
	 * @return string
	 */
	private function get_dashboard_bootstrap_sync_status_key( string $site_hash ): string {
		return 'bbai_dashboard_bootstrap_sync_' . md5( $site_hash );
	}

	/**
	 * Fetch backend dashboard truth with a local fallback so the dashboard never
	 * hard-fails when the backend endpoint is temporarily unavailable.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_logged_in_dashboard_state_truth_payload(): ?array {
		$api_client = $this->core->get_api_client();
		if ( $api_client && method_exists( $api_client, 'get_dashboard_state_truth' ) ) {
			$truth = $api_client->get_dashboard_state_truth();
			if ( is_array( $truth ) && [] !== $truth ) {
				return $truth;
			}
		}

		return $this->build_logged_in_dashboard_state_truth_fallback();
	}

	/**
	 * Build the lightweight plan context used when mapping truth into the
	 * existing hero/donut/CTA UI contract.
	 *
	 * @return array<string,mixed>
	 */
	private function get_logged_in_dashboard_plan_context(): array {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';

		$api_client = $this->core->get_api_client();
		if ( ! $api_client ) {
			return [
				'is_pro'    => false,
				'plan_slug' => 'free',
				'user_type' => 'free',
			];
		}

		$auth_state            = \BeepBeepAI\AltTextGenerator\Auth_State::resolve( $api_client );
		$has_connected_account = (bool) ( $auth_state['has_connected_account'] ?? false );
		$usage_stats           = \BeepBeepAI\AltTextGenerator\Services\Usage_Helper::get_usage(
			$api_client,
			$has_connected_account
		);
		$plan_slug             = strtolower(
			(string) ( $usage_stats['plan_type'] ?? $usage_stats['plan'] ?? $auth_state['plan_slug'] ?? 'free' )
		);
		$is_pro                = in_array( $plan_slug, [ 'pro', 'growth', 'agency' ], true )
			|| ! empty( $usage_stats['is_pro'] )
			|| ! empty( $auth_state['is_pro'] );

		return [
			'is_pro'    => $is_pro,
			'plan_slug' => '' !== $plan_slug ? $plan_slug : 'free',
			'user_type' => $is_pro ? 'pro' : 'free',
		];
	}

	/**
	 * Build a conservative local truth payload when the backend truth endpoint
	 * cannot be reached. This preserves the existing backend fallbacks without
	 * inventing an optimistic processing state on the client.
	 *
	 * @return array<string,mixed>
	 */
	private function build_logged_in_dashboard_state_truth_fallback(): array {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-logged-in-dashboard-resolver.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';

		$api_client = $this->core->get_api_client();
		$plan_ctx   = $this->get_logged_in_dashboard_plan_context();

		$auth_state            = $api_client
			? \BeepBeepAI\AltTextGenerator\Auth_State::resolve( $api_client )
			: [];
		$has_connected_account = (bool) ( $auth_state['has_connected_account'] ?? false );
		$usage_stats           = $api_client
			? \BeepBeepAI\AltTextGenerator\Services\Usage_Helper::get_usage( $api_client, $has_connected_account )
			: [];
		$stats_payload         = $this->core->get_dashboard_stats_payload( false );
		$coverage              = [
			'total_images' => (int) ( $stats_payload['total_images'] ?? $stats_payload['total'] ?? 0 ),
			'missing'      => (int) ( $stats_payload['images_missing_alt'] ?? $stats_payload['missing'] ?? 0 ),
			'weak'         => (int) ( $stats_payload['needs_review_count'] ?? $stats_payload['needs_review'] ?? $stats_payload['weak'] ?? 0 ),
			'optimized'    => (int) ( $stats_payload['optimized_count'] ?? $stats_payload['optimized'] ?? 0 ),
			'failed'       => (int) ( $stats_payload['failed'] ?? 0 ),
		];
		$job_data              = [];
		if ( method_exists( $this->core, 'get_active_job_status' ) ) {
			$raw_job = $this->core->get_active_job_status();
			if ( is_array( $raw_job ) && [] !== $raw_job ) {
				$job_data = $raw_job;
			}
		}

		$last_run_at = null;
		if ( method_exists( $this->core, 'get_last_job_completed_at' ) ) {
			$ts = $this->core->get_last_job_completed_at();
			if ( $ts ) {
				$last_run_at = is_numeric( $ts ) ? gmdate( 'c', (int) $ts ) : (string) $ts;
			}
		}

		$ctx   = \BeepBeepAI\AltTextGenerator\Services\Logged_In_Dashboard_Resolver::build_ctx(
			is_array( $usage_stats ) ? $usage_stats : [],
			$coverage,
			$job_data,
			[],
			$last_run_at,
			$plan_ctx
		);
		$state = \BeepBeepAI\AltTextGenerator\Services\Logged_In_Dashboard_Resolver::compute_state_id( $ctx );
		$used  = max( 0, (int) ( $ctx['credits']['used'] ?? 0 ) );
		$total = max( 1, (int) ( $ctx['credits']['total'] ?? 1 ) );
		$job   = ! empty( $ctx['job'] ) && is_array( $ctx['job'] ) ? $ctx['job'] : null;

		return [
			'state'             => $state,
			'counts'            => [
				'missing'      => (int) ( $ctx['counts']['missing'] ?? 0 ),
				'review'       => (int) ( $ctx['counts']['review'] ?? 0 ),
				'needs_review' => (int) ( $ctx['counts']['review'] ?? 0 ),
				'optimized'    => (int) ( $ctx['counts']['complete'] ?? 0 ),
				'complete'     => (int) ( $ctx['counts']['complete'] ?? 0 ),
				'failed'       => (int) ( $ctx['counts']['failed'] ?? 0 ),
				'total'        => (int) ( $ctx['mediaCount'] ?? 0 ),
			],
			'job'               => $job ? [
				'active'          => ! empty( $job['active'] ),
				'pausable'        => ! empty( $job['pausable'] ),
				'status'          => (string) ( $job['status'] ?? '' ),
				'done'            => (int) ( $job['done'] ?? 0 ),
				'total'           => (int) ( $job['total'] ?? 0 ),
				'eta_seconds'     => isset( $job['eta_seconds'] ) && is_numeric( $job['eta_seconds'] ) ? (int) $job['eta_seconds'] : null,
				'error'           => isset( $job['error'] ) ? (string) $job['error'] : null,
				'last_checked_at' => null,
			] : [
				'active'          => false,
				'pausable'        => false,
				'status'          => 'idle',
				'done'            => 0,
				'total'           => 0,
				'eta_seconds'     => null,
				'error'           => null,
				'last_checked_at' => null,
			],
			'credits'           => [
				'used'      => $used,
				'total'     => $total,
				'remaining' => max( 0, $total - $used ),
				'plan'      => (string) ( $plan_ctx['plan_slug'] ?? 'free' ),
				'plan_slug' => (string) ( $plan_ctx['plan_slug'] ?? 'free' ),
				'is_pro'    => ! empty( $plan_ctx['is_pro'] ),
			],
			'site'              => [
				'site_hash'             => $api_client && method_exists( $api_client, 'get_site_id' ) ? (string) $api_client->get_site_id() : '',
				'has_connected_account' => $has_connected_account,
			],
			'resolution_sources' => [
				'state'   => 'plugin_fallback',
				'counts'  => 'dashboard_stats_payload',
				'job'     => 'active_generation_job_store',
				'credits' => 'usage_helper',
				'site'    => 'site_identifier',
			],
			'last_run_at'       => $last_run_at,
			'fallback'          => true,
		];
	}

	/**
	 * Resolve logged-in dashboard state data for action responses.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array<string,mixed>|null
	 */
	private function get_logged_in_dashboard_state_response_data( \WP_REST_Request $request ): ?array {
		try {
			$response = $this->handle_logged_in_dashboard( $request );
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( $response instanceof \WP_REST_Response ) {
			$data = $response->get_data();
			return is_array( $data ) ? $data : null;
		}

		return is_array( $response ) ? $response : null;
	}

	/**
	 * Return usage metrics from backend.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_usage( \WP_REST_Request $request ) {
		$api_client = $this->core->get_api_client();
		if ( ! $api_client ) {
			return new \WP_Error( 'missing_client', 'API client not available.' );
		}

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';

		$auth_state = \BeepBeepAI\AltTextGenerator\Auth_State::resolve( $api_client );
		return \BeepBeepAI\AltTextGenerator\Services\Usage_Helper::get_usage(
			$api_client,
			(bool) ( $auth_state['has_connected_account'] ?? false )
		);
	}

	/**
	 * Expose checkout plans/prices.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array
	 */
	public function handle_plans( \WP_REST_Request $request ) {
		return [
			'prices' => $this->core->get_checkout_price_ids(),
		];
	}

	/**
	 * Return queue status snapshot.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array
	 */
	public function handle_queue( \WP_REST_Request $request ) {
		$stats    = Queue::get_stats();
		$recent   = Queue::get_recent( apply_filters( 'bbai_queue_recent_limit', 10 ) );
		$failures = Queue::get_recent_failures( apply_filters( 'bbai_queue_fail_limit', 5 ) );
		$active   = null;

		if ( method_exists( $this->core, 'get_active_generation_job_for_site' ) ) {
			$active = $this->core->get_active_generation_job_for_site();
		}

		return [
			'stats'      => $stats,
			'job_state'  => is_array( $active ) ? ( $active['state'] ?? 'PROCESSING' ) : 'IDLE',
			'active_job' => $active,
			'recent'     => array_map( [ $this, 'sanitize_job_row' ], $recent ),
			'failures'   => array_map( [ $this, 'sanitize_job_row' ], $failures ),
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

	/**
	 * Get user usage data for multi-user visualization.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_user_usage( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';

		$usage_tracker = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display(true);
		$total_allowed = max( 1, intval( $usage_tracker['limit'] ?? 50 ) );
		$total_used    = max( 0, intval( $usage_tracker['used'] ?? 0 ) );
		
		$users = \BeepBeepAI\AltTextGenerator\Usage\get_monthly_usage_by_user();

		// Check if current user is admin
		$is_admin = current_user_can('manage_options');
		$show_full_names = $is_admin || get_option('bbai_show_full_user_names', false);

		// Anonymize usernames for non-admins if needed
		if (!$show_full_names) {
			foreach ($users as &$user) {
				if ($user['user_id'] > 0 && $user['user_id'] !== get_current_user_id()) {
					// Show only first letter + last 3 chars of username
					$username = $user['username'];
					if (strlen($username) > 4) {
						$user['username'] = substr($username, 0, 1) . '***' . substr($username, -3);
					} else {
						$user['username'] = substr($username, 0, 1) . '***';
					}
					$user['display_name'] = substr($user['display_name'], 0, 1) . '***';
				}
			}
		}

		return [
			'total_used'    => $total_used,
			'total_allowed' => $total_allowed,
			'users'         => $users,
		];
	}

	/**
	 * Get usage events with filters.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_events( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';

		$pagination = Input_Validator::pagination( $request, 50, 100 );
		$user_id    = Input_Validator::int_param( $request, 'user_id', 0 );

		$filters = [
			'user_id'     => $user_id > 0 ? $user_id : null,
			'date_from'   => Input_Validator::string_param( $request, 'date_from' ),
			'date_to'     => Input_Validator::string_param( $request, 'date_to' ),
			'action_type' => self::sanitize_action_type_arg( $request->get_param( 'action_type' ) ),
			'per_page'    => $pagination['per_page'],
			'page'        => $pagination['page'],
		];

		$result = \BeepBeepAI\AltTextGenerator\Usage\get_usage_events( $filters );

		// Check if current user is admin
		$is_admin = current_user_can('manage_options');
		$show_full_names = $is_admin || get_option('bbai_show_full_user_names', false);

		// Anonymize usernames for non-admins if needed
		if (!$show_full_names) {
			foreach ($result['events'] as &$event) {
				if ($event['user_id'] > 0 && $event['user_id'] !== get_current_user_id()) {
					$username = $event['username'];
					if (strlen($username) > 4) {
						$event['username'] = substr($username, 0, 1) . '***' . substr($username, -3);
					} else {
						$event['username'] = substr($username, 0, 1) . '***';
					}
					$event['display_name'] = substr($event['display_name'], 0, 1) . '***';
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

		$user_id = get_current_user_id();
		$tokens_used = Input_Validator::int_param($request, 'tokens_used', 1, 1);
		$action_type = Input_Validator::key_param($request, 'action_type', 'generate');
		$image_id = Input_Validator::int_param($request, 'image_id', 0);
		$post_id = Input_Validator::int_param($request, 'post_id', 0);
		$context = [
			'image_id' => $image_id > 0 ? $image_id : null,
			'post_id'  => $post_id > 0 ? $post_id : null,
		];

		$result = \BeepBeepAI\AltTextGenerator\Usage\record_usage_event($user_id, $tokens_used, $action_type, $context);

		if ($result === false) {
			return new \WP_Error('log_failed', 'Failed to log usage event.', ['status' => 500]);
		}

		return [
			'success' => true,
			'id'      => $result,
		];
	}

	/**
	 * Get trial quota status.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array
	 */
	public function handle_trial_status( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-trial-quota.php';

		$status = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status();
		$status['anon_cookie_name'] = \BeepBeepAI\AltTextGenerator\bbai_get_anon_cookie_name();

		return rest_ensure_response( $status );
	}

	/**
	 * Get usage summary (site-wide quota)
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_usage_summary( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		
		$usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display(true);
		
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		
		return [
			'site_id' => $site_id,
			'total_limit' => $usage['limit'] ?? 0,
			'total_used' => $usage['used'] ?? 0,
			'remaining' => $usage['remaining'] ?? 0,
			'resets_at' => $usage['reset_timestamp'] ?? 0,
			'plan_type' => $usage['plan_type'] ?? 'free',
		];
	}

	/**
	 * Get usage breakdown by user
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_usage_by_user( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';
		
		$users = \BeepBeepAI\AltTextGenerator\Usage\get_monthly_usage_by_user();
		
		// Format response
		$formatted = [];
		foreach ($users as $user) {
			$formatted[] = [
				'user_id' => intval($user['user_id']),
				'display_name' => $user['display_name'] ?? $user['username'] ?? __('System', 'beepbeep-ai-alt-text-generator'),
				'username' => $user['username'] ?? '',
				'tokens_used' => intval($user['tokens_used'] ?? 0),
				'last_used' => $user['last_used'] ?? null,
			];
		}
		
		return $formatted;
	}

	/**
	 * Get usage events with filters
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array|\WP_Error
	 */
	public function handle_usage_events( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';

		$pagination         = Input_Validator::pagination( $request, 50, 100 );
		$user_id            = Input_Validator::int_param( $request, 'user_id', 0 );
		$allowed_actions    = [ 'generate', 'regenerate', 'bulk', 'api', 'upload', 'inline', 'queue', 'manual', 'onboarding' ];
		$action_type        = Input_Validator::key_param( $request, 'action_type', '', $allowed_actions );

		$filters = [
			'user_id'     => $user_id > 0 ? $user_id : null,
			'date_from'   => Input_Validator::string_param( $request, 'from' ),
			'date_to'     => Input_Validator::string_param( $request, 'to' ),
			'action_type' => $action_type ?: null,
			'per_page'    => $pagination['per_page'],
			'page'        => $pagination['page'],
		];

		$result = \BeepBeepAI\AltTextGenerator\Usage\get_usage_events( $filters );

		return $result;
	}

	/**
	 * Phase 17 — Lightweight in-product assistant (rule-based + filter for LLM).
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_assistant_chat( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/automation/class-bbai-phase17-assistant.php';

		$params  = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = [];
		}
		$message = isset( $params['message'] ) ? sanitize_text_field( (string) $params['message'] ) : '';
		$context = isset( $params['context'] ) && is_array( $params['context'] ) ? $params['context'] : [];

		$safe_context = [];
		foreach ( $context as $k => $v ) {
			$key = is_string( $k ) ? sanitize_key( $k ) : '';
			if ( '' === $key ) {
				continue;
			}
			if ( is_scalar( $v ) ) {
				$safe_context[ $key ] = is_string( $v ) ? sanitize_text_field( (string) $v ) : $v;
			}
		}

		$out = \BeepBeepAI\AltTextGenerator\Phase17_Assistant::reply( $message, $safe_context );

		if ( function_exists( 'bbai_telemetry_emit' ) ) {
			bbai_telemetry_emit(
				'phase17_assistant_used',
				[
					'mode' => isset( $out['mode'] ) ? (string) $out['mode'] : 'unknown',
				]
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Phase 17 — One-click ALT improvement (uses existing regenerate path; does not change scoring).
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_improve_alt( \WP_REST_Request $request ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/automation/class-bbai-phase17-assistant.php';

		$id = absint( $request->get_param( 'id' ) );
		if ( $id <= 0 ) {
			return new \WP_Error( 'invalid_attachment', __( 'Invalid attachment ID.', 'beepbeep-ai-alt-text-generator' ), [ 'status' => 400 ] );
		}

		$prev_alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		$tip      = \BeepBeepAI\AltTextGenerator\Phase17_Assistant::suggest_text_only( $prev_alt );

		$output_started = ob_get_level() > 0;
		if ( ! $output_started ) {
			ob_start();
		}

		try {
			$alt = $this->core->generate_and_save( $id, 'ajax', 0, [], true );

			if ( is_wp_error( $alt ) ) {
				if ( ! $output_started ) {
					ob_end_clean();
				}
				$code = $alt->get_error_code();
				$status = 500;
				if ( in_array( $code, [ 'limit_reached', 'bbai_trial_exhausted' ], true ) ) {
					$status = 403;
				} elseif ( in_array( $code, [ 'auth_required', 'user_not_found' ], true ) ) {
					$status = 401;
				}
				return new \WP_Error( $code, $alt->get_error_message(), [ 'status' => $status, 'text_only_tip' => $tip ] );
			}

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

			if ( function_exists( 'bbai_telemetry_emit' ) ) {
				bbai_telemetry_emit( 'phase17_improve_alt_applied', [ 'attachment_id' => $id ] );
			}

			return rest_ensure_response(
				[
					'id'            => $id,
					'alt'           => $alt,
					'meta'          => $meta,
					'stats'         => $stats,
					'text_only_tip' => $tip,
					'improve'       => true,
				]
			);
		} catch ( \Exception $e ) {
			if ( ! $output_started ) {
				ob_end_clean();
			}
			return new \WP_Error( 'improve_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

}
