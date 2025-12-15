<?php
/**
 * API Request Handler
 *
 * Handles core HTTP request logic, retries, and header construction for API calls.
 * Manages authentication headers (JWT tokens and license keys), request retries,
 * and error handling.
 *
 * @package BeepBeepAI\AltTextGenerator\API
 * @since 4.2.0
 */

namespace BeepBeepAI\AltTextGenerator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Class API_Request_Handler
 *
 * Core HTTP request handler for backend API communication.
 * Handles authentication, retries, and response processing.
 *
 * @since 4.2.0
 */
class API_Request_Handler {

	/**
	 * Base API URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Callback to get JWT token.
	 *
	 * @var callable|null
	 */
	private $get_token_callback;

	/**
	 * Callback to get license key.
	 *
	 * @var callable|null
	 */
	private $get_license_key_callback;

	/**
	 * Callback to get site identifier.
	 *
	 * @var callable|null
	 */
	private $get_site_id_callback;

	/**
	 * Callback to check if user has active license.
	 *
	 * @var callable|null
	 */
	private $has_active_license_callback;

	/**
	 * Callback to log API events.
	 *
	 * @var callable|null
	 */
	private $log_api_event_callback;

	/**
	 * Error handler instance.
	 *
	 * @var API_Error_Handler|null
	 */
	private $error_handler;

	/**
	 * Constructor.
	 *
	 * @since 4.2.0
	 *
	 * @param string                 $api_url      Base URL for the API.
	 * @param array                  $callbacks    Array of callback functions.
	 * @param API_Error_Handler|null $error_handler Optional error handler instance.
	 */
	public function __construct( $api_url, $callbacks = array(), $error_handler = null ) {
		$this->api_url                     = $api_url;
		$this->get_token_callback          = $callbacks['get_token'] ?? null;
		$this->get_license_key_callback    = $callbacks['get_license_key'] ?? null;
		$this->get_site_id_callback        = $callbacks['get_site_id'] ?? null;
		$this->has_active_license_callback = $callbacks['has_active_license'] ?? null;
		$this->log_api_event_callback      = $callbacks['log_api_event'] ?? null;
		$this->error_handler               = $error_handler;
	}

	/**
	 * Set error handler instance.
	 *
	 * @since 4.2.0
	 *
	 * @param API_Error_Handler $error_handler Error handler instance.
	 * @return void
	 */
	public function set_error_handler( $error_handler ) {
		$this->error_handler = $error_handler;
	}

	/**
	 * Get authentication headers for API requests.
	 *
	 * Constructs headers with license key (preferred) or JWT token.
	 * Includes site hash, site URL, and optional user ID.
	 * Checks extra_headers first for license key to handle cases where
	 * callback hasn't refreshed yet.
	 *
	 * @since 4.2.0
	 *
	 * @param bool  $include_user_id Whether to include current user ID in headers.
	 * @param array $extra_headers    Additional headers to merge (license key may be here).
	 * @return array Associative array of HTTP headers.
	 */
	public function get_auth_headers( $include_user_id = false, $extra_headers = array() ) {
		$token       = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';
		$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';

		// CRITICAL: Check if license key is provided in extra_headers first
		// This handles cases where Generation Client explicitly adds it but callback hasn't refreshed
		if ( empty( $license_key ) && ! empty( $extra_headers ) && is_array( $extra_headers ) && isset( $extra_headers['X-License-Key'] ) && ! empty( $extra_headers['X-License-Key'] ) ) {
			$license_key = $extra_headers['X-License-Key'];

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'info',
					'License key found in extra_headers - using it for auth',
					array(
						'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
						'source'              => 'extra_headers',
					),
					'auth'
				);
			}
		}

		// CRITICAL: If we have an active license but no key, try to retrieve it
		// This handles cases where license data exists but key wasn't stored separately
		if ( empty( $license_key ) && $this->has_active_license_callback && call_user_func( $this->has_active_license_callback ) ) {
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Active license detected but no license key found - attempting to retrieve',
					array(
						'has_active_license' => true,
						'has_license_key'    => false,
					),
					'auth'
				);
			}

			// Note: License key retrieval logic will be handled by License_Manager
			// This is just a placeholder for the header construction
		}

		$site_id = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';
		if ( empty( $site_id ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
			$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		}

		// Get site fingerprint for abuse prevention
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
		$fingerprint = \BeepBeepAI\AltTextGenerator\Site_Fingerprint::get_fingerprint();

		$headers = array(
			'Content-Type'       => 'application/json',
			'X-Site-Hash'        => $site_id,  // Site-based licensing identifier - ensures quota tracking per-site, not per-user
			'X-Site-URL'         => get_site_url(),  // For backend reference
			'X-Site-Fingerprint' => $fingerprint,  // Site fingerprint for abuse prevention
		);

		// Include current user ID if requested (for analytics)
		if ( $include_user_id ) {
			$user_id = get_current_user_id();
			if ( $user_id > 0 ) {
				$headers['X-WP-User-ID'] = (string) $user_id;
			}
		}

		// CRITICAL: Backend /api/generate endpoint requires BOTH license key AND JWT token
		// Send both headers when available to ensure proper authentication
		if ( ! empty( $license_key ) ) {
			$headers['X-License-Key'] = $license_key;

			// Debug logging for license key usage
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'debug',
					'License key included in auth headers',
					array(
						'has_license_key'     => true,
						'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
						'has_active_license'  => $this->has_active_license_callback ? call_user_func( $this->has_active_license_callback ) : false,
						'source'              => isset( $extra_headers['X-License-Key'] ) ? 'extra_headers' : 'callback',
					),
					'auth'
				);
			}
		}

		// CRITICAL: Always send JWT token when available (backend requires both)
		// The generate endpoint needs both X-License-Key AND Authorization headers
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;

			// Debug logging if we have license but no key
			if ( ! empty( $license_key ) && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'debug',
					'Both license key and JWT token included in auth headers',
					array(
						'has_license_key' => true,
						'has_token'       => true,
					),
					'auth'
				);
			} elseif ( $this->has_active_license_callback && call_user_func( $this->has_active_license_callback ) && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Active license detected but no license key available for headers',
					array(
						'has_active_license' => true,
						'has_license_key'    => false,
						'has_token'          => ! empty( $token ),
					),
					'auth'
				);
			}
		} else {
			// No JWT token available - this is OK if we have a license key
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				$log_level = ! empty( $license_key ) ? 'debug' : 'warning';
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					$log_level,
					'No JWT token available for auth headers',
					array(
						'has_license_key'    => ! empty( $license_key ),
						'has_token'          => false,
						'has_active_license' => $this->has_active_license_callback ? call_user_func( $this->has_active_license_callback ) : false,
					),
					'auth'
				);
			}
		}

		// Merge any caller-provided headers (used for debugging/traceability)
		// CRITICAL: Merge AFTER setting auth headers to ensure X-License-Key from extra_headers takes precedence
		if ( ! empty( $extra_headers ) && is_array( $extra_headers ) ) {
			$headers = array_merge( $headers, $extra_headers );
		}

		return $headers;
	}

	/**
	 * Make HTTP request to API
	 * Returns array with status_code, data, success OR WP_Error
	 * Note: Error handling is done by API_Error_Handler
	 */
	public function make_request( $endpoint, $method = 'GET', $data = null, $timeout = null, $include_user_id = false, $extra_headers = array(), $include_auth_headers = true ) {
		$url     = trailingslashit( $this->api_url ) . ltrim( $endpoint, '/' );
		$headers = $include_auth_headers
			? $this->get_auth_headers( $include_user_id, $extra_headers )
			: array_merge(
				array(
					'Content-Type' => 'application/json',
				),
				$extra_headers
			);

		// Use longer timeout for generation requests (OpenAI can take time)
		if ( $timeout === null ) {
			// Check for generate endpoint (with or without leading slash)
			$endpoint_str         = is_string( $endpoint ) ? $endpoint : '';
			$is_generate_endpoint = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || strpos( $endpoint_str, 'api/generate' ) !== false );
			$timeout              = $is_generate_endpoint ? 90 : 30;
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => $timeout,
		);

		if ( $data && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		// Log request details for debugging (especially for generate endpoint)
		$endpoint_str         = is_string( $endpoint ) ? $endpoint : '';
		$is_generate_endpoint = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || strpos( $endpoint_str, 'api/generate' ) !== false );

		$log_data = array(
			'endpoint' => $endpoint,
			'method'   => $method,
		);

		// For generate endpoint, log auth headers (but not sensitive values)
		if ( $is_generate_endpoint && $include_auth_headers ) {
			$auth_headers                = $this->get_auth_headers( $include_user_id, $extra_headers );
			$log_data['has_license_key'] = ! empty( $auth_headers['X-License-Key'] );
			$log_data['has_auth_token']  = ! empty( $auth_headers['Authorization'] );
			$log_data['site_hash']       = $auth_headers['X-Site-Hash'] ?? 'missing';
			$log_data['site_url']        = $auth_headers['X-Site-URL'] ?? 'missing';
			$log_data['has_user_id']     = ! empty( $auth_headers['X-WP-User-ID'] );
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'info',
					'Generate request headers',
					array(
						'has_license_key'     => $log_data['has_license_key'],
						'has_auth_token'      => $log_data['has_auth_token'],
						'site_hash'           => $log_data['site_hash'],
						'site_url'            => $log_data['site_url'],
						'has_user_id'         => $log_data['has_user_id'],
						'license_key_preview' => ! empty( $auth_headers['X-License-Key'] ) ? substr( $auth_headers['X-License-Key'], 0, 20 ) . '...' : 'none',
					),
					'api'
				);
			}
		}

		$this->log_api_event( 'debug', 'API request started', $log_data );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_message = is_string( $error_message ) ? $error_message : '';
			$this->log_api_event(
				'error',
				'API request failed',
				array(
					'endpoint' => $endpoint,
					'method'   => $method,
					'error'    => $error_message,
				)
			);
			$error_message_str = is_string( $error_message ) ? $error_message : '';
			if ( $error_message_str && strpos( $error_message_str, 'timeout' ) !== false ) {
				// Provide more specific message for generation timeouts
				$endpoint_str         = is_string( $endpoint ) ? $endpoint : '';
				$is_generate_endpoint = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || strpos( $endpoint_str, 'api/generate' ) !== false );
				if ( $is_generate_endpoint ) {
					return new \WP_Error( 'api_timeout', __( 'The image generation is taking longer than expected. This may happen with large images or during high server load. Please try again.', 'beepbeep-ai-alt-text-generator' ) );
				}
				return new \WP_Error( 'api_timeout', __( 'The server is taking too long to respond. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' ) );
			} elseif ( $error_message_str && strpos( $error_message_str, 'could not resolve' ) !== false ) {
				return new \WP_Error( 'api_unreachable', __( 'Unable to reach authentication server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator' ) );
			}
			return new \WP_Error( 'api_error', $error_message ?: __( 'API request failed', 'beepbeep-ai-alt-text-generator' ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		$this->log_api_event(
			$status_code >= 400 ? 'warning' : 'debug',
			'API response received',
			array(
				'endpoint' => $endpoint,
				'method'   => $method,
				'status'   => $status_code,
			)
		);

		// Enhanced logging for 401/403 errors to debug license validation issues
		if ( ( $status_code === 401 || $status_code === 403 ) && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
			$site_id     = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'warning',
				'401/403 response received - logging full response structure',
				array(
					'endpoint'            => $endpoint,
					'status_code'         => $status_code,
					'response_body'       => $body,
					'parsed_data'         => $data,
					'has_active_license'  => $this->has_active_license_callback ? call_user_func( $this->has_active_license_callback ) : false,
					'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
					'site_hash'           => $site_id,
				),
				'api'
			);
		}

		// Build response array
		$response = array(
			'status_code' => $status_code,
			'data'        => $data,
			'body'        => $body,
			'success'     => $status_code >= 200 && $status_code < 300,
			'endpoint'    => $endpoint,
			'method'      => $method,
		);

		// Process response through error handler if available
		if ( $this->error_handler ) {
			return $this->error_handler->process_response( $response );
		}

		// Return raw response if no error handler
		return $response;
	}

	/**
	 * Make API request with automatic retry logic.
	 *
	 * Attempts the request up to max_attempts times, with exponential backoff
	 * for retryable errors. Handles network errors, timeouts, and server errors.
	 * Non-retryable errors (4xx client errors) are returned immediately.
	 *
	 * @since 4.2.0
	 *
	 * @param string     $endpoint            API endpoint path.
	 * @param string     $method              HTTP method (GET, POST, etc.).
	 * @param array|null $data             Request body data (for POST/PUT requests).
	 * @param int        $max_attempts        Maximum number of retry attempts (default: 3).
	 * @param bool       $include_user_id     Whether to include current user ID in headers.
	 * @param array      $extra_headers        Additional headers to include.
	 * @param bool       $include_auth_headers Whether to include authentication headers.
	 * @return array|\WP_Error Response array with 'status_code', 'data', 'success' keys, or WP_Error on failure.
	 */
	public function request_with_retry( $endpoint, $method = 'GET', $data = null, $max_attempts = 3, $include_user_id = false, $extra_headers = array(), $include_auth_headers = true ) {
		$attempt    = 0;
		$last_error = null;

		while ( $attempt < $max_attempts ) {
			$response = $this->make_request( $endpoint, $method, $data, null, $include_user_id, $extra_headers, $include_auth_headers );
			if ( ! is_wp_error( $response ) ) {
				if ( $attempt > 0 && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'info',
						'API request recovered after retry',
						array(
							'endpoint' => $endpoint,
							'attempt'  => $attempt + 1,
						),
						'api'
					);
				}
				return $response;
			}

			if ( ! $this->should_retry_api_error( $response ) ) {
				return $response;
			}

			++$attempt;
			$last_error = $response;

			if ( $attempt < $max_attempts ) {
				$delay = min( 3, $attempt ); // 1s, 2s
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'warning',
						'Retrying API request after transient failure',
						array(
							'endpoint'    => $endpoint,
							'attempt'     => $attempt + 1,
							'error_code'  => $response->get_error_code(),
							'status_code' => $response->get_error_data()['status_code'] ?? null,
						),
						'api'
					);
				}
				usleep( $delay * 1000000 );
			}
		}

		return $last_error ?: new \WP_Error( 'api_error', __( 'Unknown API error', 'beepbeep-ai-alt-text-generator' ) );
	}

	/**
	 * Determine if an API error should be retried.
	 *
	 * Checks if the error is retryable (network errors, timeouts, server errors).
	 * Non-retryable errors include authentication failures and client errors (4xx).
	 *
	 * @since 4.2.0
	 *
	 * @param \WP_Error $error The error to check.
	 * @return bool True if error should be retried, false otherwise.
	 */
	private function should_retry_api_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$retryable_codes = array( 'server_error', 'api_timeout', 'api_unreachable', 'quota_check_mismatch' );
		$code            = $error->get_error_code();
		if ( ! in_array( $code, $retryable_codes, true ) ) {
			return false;
		}

		$data   = $error->get_error_data();
		$status = isset( $data['status_code'] ) ? intval( $data['status_code'] ) : 0;

		// Retry network errors without status codes, and HTTP 5xx responses.
		if ( $status === 0 ) {
			return true;
		}

		return in_array( $status, array( 500, 502, 503, 504 ), true );
	}

	/**
	 * Log API event
	 */
	/**
	 * Log API event using the configured callback.
	 *
	 * @since 4.2.0
	 *
	 * @param string $level   Log level (debug, info, warning, error).
	 * @param string $message Log message.
	 * @param array  $context  Additional context data.
	 * @return void
	 */
	private function log_api_event( $level, $message, $context = array() ) {
		if ( $this->log_api_event_callback ) {
			call_user_func( $this->log_api_event_callback, $level, $message, $context );
		} elseif ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log( $level, $message, $context, 'api' );
		}
	}
}
