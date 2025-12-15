<?php
/**
 * API Error Handler
 *
 * Processes API responses and converts HTTP errors to WP_Error objects.
 * Handles various error types including authentication, subscription, quota,
 * and server errors with appropriate error codes and messages.
 *
 * @package BeepBeepAI\AltTextGenerator\API
 * @since 4.2.0
 */

namespace BeepBeepAI\AltTextGenerator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Class API_Error_Handler
 *
 * Processes API responses and converts errors to WordPress WP_Error objects.
 * Provides detailed error handling for different HTTP status codes and error types.
 *
 * @since 4.2.0
 */
class API_Error_Handler {

	/**
	 * Callback to get usage information.
	 *
	 * @var callable|null
	 */
	private $get_usage_callback;

	/**
	 * Callback to clear JWT token.
	 *
	 * @var callable|null
	 */
	private $clear_token_callback;

	/**
	 * Callback to check if user has active license.
	 *
	 * @var callable|null
	 */
	private $has_active_license_callback;

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
	 * Callback to log API events.
	 *
	 * @var callable|null
	 */
	private $log_api_event_callback;

	/**
	 * Constructor.
	 *
	 * @since 4.2.0
	 *
	 * @param array $callbacks Array of callback functions for various operations.
	 */
	public function __construct( $callbacks = array() ) {
		$this->get_usage_callback          = $callbacks['get_usage'] ?? null;
		$this->clear_token_callback        = $callbacks['clear_token'] ?? null;
		$this->has_active_license_callback = $callbacks['has_active_license'] ?? null;
		$this->get_license_key_callback    = $callbacks['get_license_key'] ?? null;
		$this->get_site_id_callback        = $callbacks['get_site_id'] ?? null;
		$this->log_api_event_callback      = $callbacks['log_api_event'] ?? null;
	}

	/**
	 * Process API response and convert errors to WP_Error.
	 *
	 * Analyzes the API response and converts HTTP errors to appropriate
	 * WP_Error objects with error codes and user-friendly messages.
	 * Handles various error types: 404, 401/403, 402, 429, 5xx, etc.
	 *
	 * @since 4.2.0
	 *
	 * @param array|\WP_Error $response API response array or WP_Error from network request.
	 * @return array|\WP_Error Original response array on success, WP_Error on failure.
	 */
	public function process_response( $response ) {
		// If already a WP_Error (network error), return as-is
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// If not an array, return as-is (shouldn't happen)
		if ( ! is_array( $response ) ) {
			return $response;
		}

		$status_code = isset( $response['status_code'] ) ? intval( $response['status_code'] ) : 0;
		$data        = $response['data'] ?? array();
		$body        = $response['body'] ?? '';
		$endpoint    = $response['endpoint'] ?? '';
		$method      = $response['method'] ?? 'GET';

		// Handle 404 - endpoint not found
		if ( $status_code === 404 ) {
			return $this->handle_404_error( $data, $body, $endpoint );
		}

		// Handle NO_ACCESS errors (can be in any status code response, including 403)
		$no_access_error = $this->handle_no_access_error( $data, $status_code, $endpoint, $method );
		if ( $no_access_error ) {
			return $no_access_error;
		}

		// Handle subscription errors (402 Payment Required)
		if ( $status_code === 402 ) {
			return $this->handle_subscription_error( $data, $endpoint, $method );
		}

		// Handle server errors (5xx)
		if ( $status_code >= 500 ) {
			return $this->handle_server_error( $data, $body, $status_code, $endpoint, $method );
		}

		// Clear subscription error transient on successful API calls
		if ( $status_code >= 200 && $status_code < 300 ) {
			delete_transient( 'bbai_subscription_error' );
		}

		// Handle authentication errors (401/403)
		if ( $status_code === 401 || $status_code === 403 ) {
			return $this->handle_auth_error( $data, $status_code, $endpoint );
		}

		// Success - return response as-is
		return $response;
	}

	/**
	 * Handle 404 errors
	 */
	private function handle_404_error( $data, $body, $endpoint ) {
		$body_str     = is_string( $body ) ? $body : '';
		$endpoint_str = is_string( $endpoint ) ? $endpoint : '';

		if ( $body_str && ( strpos( $body_str, '<html' ) !== false || strpos( $body_str, 'Cannot POST' ) !== false || strpos( $body_str, 'Cannot GET' ) !== false ) ) {
			$error_message = __( 'This feature is not yet available. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator' );

			if ( $endpoint_str && ( strpos( $endpoint_str, '/auth/forgot-password' ) !== false || strpos( $endpoint_str, '/auth/reset-password' ) !== false ) ) {
				$error_message = __( 'Password reset functionality is currently being set up on our backend. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator' );
			} elseif ( $endpoint_str && ( strpos( $endpoint_str, '/licenses/sites' ) !== false || strpos( $endpoint_str, '/api/licenses/sites' ) !== false ) ) {
				$error_message = __( 'License site usage tracking is currently being set up on our backend. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator' );
			}

			return new \WP_Error( 'endpoint_not_found', $error_message );
		}

		return new \WP_Error(
			'not_found',
			$data['error'] ?? $data['message'] ?? __( 'The requested resource was not found.', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Handle NO_ACCESS errors
	 */
	private function handle_no_access_error( $data, $status_code, $endpoint, $method ) {
		$no_access_code = null;
		$no_access_data = null;

		if ( is_array( $data ) ) {
			// Check top-level code field (case-insensitive)
			if ( isset( $data['code'] ) ) {
				$code_str = is_string( $data['code'] ) ? strtoupper( $data['code'] ) : '';
				if ( $code_str === 'NO_ACCESS' || $code_str === 'NOACCESS' ) {
					$no_access_code = $data['code'];
					$no_access_data = $data;
				}
			}

			// Also check nested data.code
			if ( ! $no_access_code && isset( $data['data'] ) && is_array( $data['data'] ) ) {
				if ( isset( $data['data']['code'] ) ) {
					$code_str = is_string( $data['data']['code'] ) ? strtoupper( $data['data']['code'] ) : '';
					if ( $code_str === 'NO_ACCESS' || $code_str === 'NOACCESS' ) {
						$no_access_code = $data['data']['code'];
						$no_access_data = $data['data'];
					}
				}
			}

			// Also check for no_access boolean flag
			if ( ! $no_access_code && ( isset( $data['no_access'] ) && $data['no_access'] === true ) ) {
				$no_access_code = 'no_access';
				$no_access_data = $data;
			}

			// Check nested data.no_access
			if ( ! $no_access_code && isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data']['no_access'] ) && $data['data']['no_access'] === true ) {
				$no_access_code = 'no_access';
				$no_access_data = $data['data'];
			}
		}

		if ( ! $no_access_code || ! $no_access_data ) {
			return null;
		}

		// Extract error fields
		$credits              = isset( $no_access_data['credits'] ) ? intval( $no_access_data['credits'] ) : null;
		$subscription_expired = isset( $no_access_data['subscription_expired'] ) ? (bool) $no_access_data['subscription_expired'] : false;
		$reason               = isset( $no_access_data['reason'] ) && is_string( $no_access_data['reason'] ) ? $no_access_data['reason'] : '';
		$message              = isset( $no_access_data['message'] ) && is_string( $no_access_data['message'] ) ? $no_access_data['message'] : '';

		// Fallback to top-level data
		if ( $credits === null && isset( $data['credits'] ) ) {
			$credits = intval( $data['credits'] );
		}
		if ( ! $subscription_expired && isset( $data['subscription_expired'] ) ) {
			$subscription_expired = (bool) $data['subscription_expired'];
		}
		if ( empty( $reason ) && isset( $data['reason'] ) && is_string( $data['reason'] ) ) {
			$reason = $data['reason'];
		}
		if ( empty( $message ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
			$message = $data['message'];
		}

		// Determine error type
		$error_code     = 'no_access';
		$is_quota_error = false;

		if ( ! empty( $reason ) && strtolower( $reason ) === 'no_credits' ) {
			$error_code     = 'out_of_credits';
			$is_quota_error = true;
		} elseif ( $subscription_expired ) {
			$error_code = 'subscription_expired';
		} elseif ( $credits !== null && $credits === 0 ) {
			$error_code     = 'out_of_credits';
			$is_quota_error = true;
		}

		// If it's a quota error, check if cached usage disagrees
		if ( $is_quota_error ) {
			$quota_check = $this->check_quota_mismatch();
			if ( $quota_check ) {
				return $quota_check;
			}
		}

		// Build error message
		$error_message = '';
		if ( ! empty( $message ) ) {
			$error_message = $message;
		} elseif ( ! empty( $reason ) ) {
			$error_message = $reason;
		} elseif ( $subscription_expired ) {
			$error_message = __( 'Your subscription has expired. Please renew to continue.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $credits === 0 || $is_quota_error ) {
			$error_message = __( "You've run out of credits. Please purchase more credits to continue.", 'beepbeep-ai-alt-text-generator' );
		} else {
			$error_message = __( 'Access denied. Please upgrade or purchase credits to continue.', 'beepbeep-ai-alt-text-generator' );
		}

		$this->log_api_event(
			'warning',
			'NO_ACCESS error detected',
			array(
				'endpoint'             => $endpoint,
				'method'               => $method,
				'error_code'           => $error_code,
				'credits'              => $credits,
				'subscription_expired' => $subscription_expired,
				'reason'               => $reason,
				'is_quota_error'       => $is_quota_error,
			)
		);

		// Cache NO_ACCESS error for UI handling
		set_transient(
			'bbai_no_access_error',
			array(
				'error_code'           => $error_code,
				'message'              => $error_message,
				'reason'               => $reason,
				'credits'              => $credits,
				'subscription_expired' => $subscription_expired,
				'timestamp'            => time(),
			),
			HOUR_IN_SECONDS
		);

		return new \WP_Error(
			'no_access',
			$error_message,
			array(
				'no_access'            => true,
				'error_code'           => $error_code,
				'reason'               => $reason,
				'credits'              => $credits,
				'subscription_expired' => $subscription_expired,
				'status_code'          => $status_code,
				'requires_action'      => true,
			)
		);
	}

	/**
	 * Handle subscription errors (402)
	 */
	private function handle_subscription_error( $data, $endpoint, $method ) {
		$error_code    = '';
		$error_message = '';

		if ( is_array( $data ) ) {
			$error_code    = is_string( $data['error'] ?? $data['code'] ?? '' ) ? ( $data['error'] ?? $data['code'] ?? '' ) : 'subscription_required';
			$error_message = is_string( $data['message'] ?? '' ) ? $data['message'] : __( 'A subscription is required to continue.', 'beepbeep-ai-alt-text-generator' );
		} else {
			$error_code    = 'subscription_required';
			$error_message = __( 'A subscription is required to continue.', 'beepbeep-ai-alt-text-generator' );
		}

		// Normalize error codes
		// CRITICAL: Ensure error_code is a string before using strpos (PHP 8.1+ compatibility)
		$error_code_str  = is_string( $error_code ) ? $error_code : '';
		$normalized_code = 'subscription_required';
		if ( $error_code === 'subscription_expired' || ( ! empty( $error_code_str ) && strpos( strtolower( $error_code_str ), 'expired' ) !== false ) ) {
			$normalized_code = 'subscription_expired';
		} elseif ( $error_code === 'quota_exceeded' || ( ! empty( $error_code_str ) && strpos( strtolower( $error_code_str ), 'quota' ) !== false ) || $error_code === 'out_of_credits' ) {
			$normalized_code = 'out_of_credits';
		}

		// If it's an out_of_credits error, check if cached usage disagrees
		if ( $normalized_code === 'out_of_credits' ) {
			$quota_check = $this->check_quota_mismatch();
			if ( $quota_check ) {
				return $quota_check;
			}
		}

		$this->log_api_event(
			'warning',
			'Subscription error detected',
			array(
				'endpoint'   => $endpoint,
				'method'     => $method,
				'error_code' => $normalized_code,
			)
		);

		// Cache subscription error for banner display
		set_transient(
			'bbai_subscription_error',
			array(
				'error_code' => $normalized_code,
				'message'    => $error_message,
				'timestamp'  => time(),
			),
			HOUR_IN_SECONDS
		);

		return new \WP_Error(
			'subscription_error',
			$error_message,
			array(
				'subscription_error'    => true,
				'error_code'            => $normalized_code,
				'status_code'           => 402,
				'requires_subscription' => true,
			)
		);
	}

	/**
	 * Handle server errors (5xx)
	 */
	private function handle_server_error( $data, $body, $status_code, $endpoint, $method ) {
		$error_details = '';
		if ( is_array( $data ) && isset( $data['error'] ) ) {
			$error_details = is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
		} elseif ( is_array( $data ) && isset( $data['message'] ) ) {
			$error_details = is_string( $data['message'] ) ? $data['message'] : '';
		} elseif ( ! empty( $body ) && strlen( $body ) < 500 ) {
			$error_details = is_string( $body ) ? $body : '';
		}

		$this->log_api_event(
			'error',
			'API server error',
			array(
				'endpoint'      => $endpoint,
				'method'        => $method,
				'status'        => $status_code,
				'error_details' => $error_details,
				'body_preview'  => is_string( $body ) ? substr( $body, 0, 200 ) : '',
			)
		);

		$backend_error_code    = '';
		$backend_error_message = '';

		if ( is_array( $data ) ) {
			$backend_error_code    = is_string( $data['code'] ?? '' ) ? $data['code'] : '';
			$backend_error_message = is_string( $data['message'] ?? $data['error'] ?? '' ) ? ( $data['message'] ?? $data['error'] ?? '' ) : '';
		}

		// Check for "User not found" errors
		$endpoint_str         = is_string( $endpoint ) ? $endpoint : '';
		$is_checkout_endpoint = strpos( $endpoint_str, '/billing/checkout' ) !== false;

		$error_details_str         = is_string( $error_details ) ? $error_details : '';
		$backend_error_message_str = is_string( $backend_error_message ) ? $backend_error_message : '';
		$error_details_lower       = strtolower( $error_details_str . ' ' . $backend_error_message_str );

		if ( strpos( $error_details_lower, 'user not found' ) !== false ||
			strpos( $error_details_lower, 'user does not exist' ) !== false ||
			strpos( $error_details_lower, 'jwt user not found' ) !== false ||
			strpos( $error_details_lower, 'inactive' ) !== false ||
			( is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) && strpos( strtolower( $data['code'] ), 'user_not_found' ) !== false ) ) {

			if ( $is_checkout_endpoint ) {
				return new \WP_Error(
					'user_not_found',
					__( 'User not found', 'beepbeep-ai-alt-text-generator' ),
					array(
						'requires_auth'   => false,
						'status_code'     => $status_code,
						'code'            => 'user_not_found',
						'backend_message' => $backend_error_message_str,
						'error_details'   => $error_details_str,
					)
				);
			}

			// Clear token for non-checkout endpoints
			if ( $this->clear_token_callback ) {
				call_user_func( $this->clear_token_callback );
			}
			delete_transient( 'bbai_token_last_check' );

			return new \WP_Error(
				'auth_required',
				__( 'Your session has expired or your account is no longer available. Please log in again.', 'beepbeep-ai-alt-text-generator' ),
				array(
					'requires_auth' => true,
					'status_code'   => $status_code,
					'code'          => 'user_not_found',
				)
			);
		}

		// Provide specific error message based on endpoint
		$error_message = __( 'The server encountered an error processing your request. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );

		$is_generate_endpoint    = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || strpos( $endpoint_str, 'api/generate' ) !== false );
		$is_auto_attach_endpoint = strpos( $endpoint_str, '/api/licenses/auto-attach' ) !== false;

		$error_details_lower = strtolower( $error_details_str . ' ' . $backend_error_message_str );
		$is_schema_error     = strpos( $error_details_lower, 'column' ) !== false &&
							( strpos( $error_details_lower, 'schema cache' ) !== false ||
							strpos( $error_details_lower, 'not found' ) !== false ||
							strpos( $error_details_lower, 'does not exist' ) !== false );

		if ( $is_auto_attach_endpoint ) {
			if ( $is_schema_error ) {
				$error_message = __( 'License auto-attachment is temporarily unavailable due to a backend maintenance issue. Your plugin will continue to work normally. Please try again later or contact support if needed.', 'beepbeep-ai-alt-text-generator' );
			} else {
				$error_message = __( 'License auto-attachment failed. Your plugin will continue to work normally. You can manually activate your license using the key from your email if needed.', 'beepbeep-ai-alt-text-generator' );
			}
		} elseif ( $is_checkout_endpoint ) {
			$error_message = __( 'Unable to create checkout session. This may be a temporary backend issue. Please try again in a moment or contact support if the problem persists.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $is_generate_endpoint ) {
			if ( $is_schema_error ) {
				$error_message = __( 'The image generation service is temporarily unavailable due to backend maintenance. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
			} else {
				$error_details_str      = is_string( $error_details ) ? $error_details : '';
				$backend_error_code_str = is_string( $backend_error_code ) ? $backend_error_code : '';
				if ( ( $error_details_str && ( strpos( strtolower( $error_details_str ), 'incorrect api key' ) !== false ||
					strpos( strtolower( $error_details_str ), 'invalid api key' ) !== false ) ) ||
					( $backend_error_code_str && strpos( strtolower( $backend_error_code_str ), 'generation_error' ) !== false ) ) {
					$error_message = __( 'The image generation service is temporarily unavailable due to a backend configuration issue. Please contact support.', 'beepbeep-ai-alt-text-generator' );
				} else {
					$error_message = __( 'The image generation service is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
				}
			}
		} elseif ( is_string( $endpoint ) && strpos( $endpoint, '/auth/' ) !== false ) {
			if ( $is_schema_error ) {
				$error_message = __( 'Authentication is temporarily unavailable due to backend maintenance. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
			} else {
				$error_message = __( 'The authentication server is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
			}
		} elseif ( $is_schema_error ) {
			$error_message = __( 'The service is temporarily unavailable due to backend maintenance. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
		}

		return new \WP_Error(
			'server_error',
			$error_message,
			array(
				'status_code'     => $status_code,
				'endpoint'        => $endpoint,
				'error_details'   => $error_details,
				'backend_code'    => $backend_error_code,
				'backend_message' => $backend_error_message,
			)
		);
	}

	/**
	 * Handle authentication errors (401/403)
	 */
	private function handle_auth_error( $data, $status_code, $endpoint ) {
		$endpoint_str         = is_string( $endpoint ) ? $endpoint : '';
		$is_checkout_endpoint = strpos( $endpoint_str, '/billing/checkout' ) !== false;

		// Check if it's a subscription error
		$is_subscription_error = false;
		if ( is_array( $data ) ) {
			$code_str = isset( $data['code'] ) && is_string( $data['code'] ) ? strtoupper( $data['code'] ) : '';
			if ( $code_str === 'NO_ACCESS' || $code_str === 'NOACCESS' ||
				( isset( $data['no_access'] ) && $data['no_access'] === true ) ||
				( isset( $data['data']['no_access'] ) && $data['data']['no_access'] === true ) ) {
				$is_subscription_error = true;
			}
		}

		// Don't clear token for checkout or subscription errors, or if user has active license
		$has_active_license = $this->has_active_license_callback ? call_user_func( $this->has_active_license_callback ) : false;

		if ( ! $is_checkout_endpoint && ! $is_subscription_error && ! $has_active_license ) {
			if ( $this->clear_token_callback ) {
				call_user_func( $this->clear_token_callback );
			}
			delete_transient( 'bbai_token_last_check' );
		}

		if ( $is_subscription_error ) {
			return new \WP_Error(
				'no_access',
				isset( $data['message'] ) && is_string( $data['message'] ) ? $data['message'] : __( 'No active subscription found. Please subscribe to continue.', 'beepbeep-ai-alt-text-generator' ),
				array(
					'no_access'       => true,
					'error_code'      => 'no_access',
					'status_code'     => $status_code,
					'requires_action' => true,
				)
			);
		}

		// If user has active license, check usage endpoint
		if ( $has_active_license ) {
			return $this->handle_license_validation_error( $data, $status_code, $endpoint );
		}

		return new \WP_Error(
			'auth_required',
			__( 'Authentication required. Please log in to continue.', 'beepbeep-ai-alt-text-generator' ),
			array( 'requires_auth' => true )
		);
	}

	/**
	 * Handle license validation errors (when user has active license but gets 401/403)
	 */
	private function handle_license_validation_error( $data, $status_code, $endpoint ) {
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
			$site_id     = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';

			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'warning',
				'401/403 received with active license - checking response structure',
				array(
					'status_code'         => $status_code,
					'endpoint'            => $endpoint,
					'response_data'       => $data,
					'has_code'            => isset( $data['code'] ),
					'code_value'          => isset( $data['code'] ) ? $data['code'] : 'none',
					'has_reason'          => isset( $data['reason'] ),
					'reason_value'        => isset( $data['reason'] ) ? $data['reason'] : 'none',
					'has_message'         => isset( $data['message'] ),
					'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
					'site_hash'           => $site_id,
				),
				'api'
			);
		}

		// Check if usage endpoint works
		$usage_check      = $this->get_usage_callback ? call_user_func( $this->get_usage_callback ) : null;
		$has_usage_access = ! is_wp_error( $usage_check ) && is_array( $usage_check );

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'info',
				'Usage endpoint check after 401/403',
				array(
					'has_usage_access' => $has_usage_access,
					'usage_remaining'  => $has_usage_access && isset( $usage_check['remaining'] ) ? $usage_check['remaining'] : 'unknown',
					'usage_error'      => is_wp_error( $usage_check ) ? $usage_check->get_error_message() : 'none',
				),
				'api'
			);
		}

		if ( $has_usage_access ) {
			// License key is valid (usage endpoint works), but generate endpoint is failing
			// Check if backend provided a specific error message
			$backend_message = '';
			if ( is_array( $data ) ) {
				$backend_message = isset( $data['message'] ) && is_string( $data['message'] ) ? $data['message'] : '';
				if ( empty( $backend_message ) && isset( $data['error'] ) && is_string( $data['error'] ) ) {
					$backend_message = $data['error'];
				}
				if ( empty( $backend_message ) && isset( $data['reason'] ) && is_string( $data['reason'] ) ) {
					$backend_message = $data['reason'];
				}
			}

			// Use backend message if available, otherwise use generic message
			if ( ! empty( $backend_message ) ) {
				$error_message = $backend_message;
			} else {
				// Backend received the key (logs show hasLicenseKey: true) but validation failed
				// This could be: invalid key, expired key, wrong site association, or permissions issue
				$error_message = __( 'License key received but validation failed on generation endpoint. Please check your license key configuration or contact support.', 'beepbeep-ai-alt-text-generator' );
			}

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
				$site_id     = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';

				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'License key works for usage but generate endpoint validation failed',
					array(
						'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
						'site_hash'           => $site_id,
						'usage_remaining'     => $usage_check['remaining'] ?? 'unknown',
						'backend_message'     => $backend_message,
						'response_data'       => $data,
					),
					'licensing'
				);
			}
		} else {
			$error_message = isset( $data['message'] ) && is_string( $data['message'] )
				? $data['message']
				: __( 'License validation failed. Please refresh the page or contact support.', 'beepbeep-ai-alt-text-generator' );
		}

		return new \WP_Error(
			'license_error',
			$error_message,
			array(
				'license_error'      => true,
				'error_code'         => isset( $data['code'] ) ? $data['code'] : 'license_validation_failed',
				'status_code'        => $status_code,
				'usage_check_passed' => $has_usage_access,
			)
		);
	}

	/**
	 * Handle 429 rate limit errors
	 */
	public function handle_rate_limit_error( $response ) {
		$response_data        = $response['data'] ?? array();
		$error_code           = isset( $response_data['code'] ) && is_string( $response_data['code'] ) ? strtoupper( $response_data['code'] ) : '';
		$is_openai_rate_limit = ( $error_code === 'OPENAI_RATE_LIMIT' ||
								( isset( $response_data['reason'] ) && strtolower( $response_data['reason'] ) === 'rate_limit_exceeded' ) );

		if ( $is_openai_rate_limit ) {
			return new \WP_Error(
				'openai_rate_limit',
				$response_data['message'] ?? __( 'OpenAI rate limit reached. Please try again later.', 'beepbeep-ai-alt-text-generator' ),
				array(
					'error_code'  => 'openai_rate_limit',
					'retry_after' => 60,
				)
			);
		}

		// Check if it's a quota issue misreported as 429
		$quota_check = $this->check_quota_mismatch();
		if ( $quota_check ) {
			return $quota_check;
		}

		return new \WP_Error(
			'limit_reached',
			$response_data['message'] ?? $response_data['error'] ?? __( 'Rate limit reached. Please try again later.', 'beepbeep-ai-alt-text-generator' ),
			array( 'usage' => $response_data['usage'] ?? null )
		);
	}

	/**
	 * Check if cached usage disagrees with backend (quota mismatch)
	 */
	private function check_quota_mismatch() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

		if ( ! is_array( $cached_usage ) || ! isset( $cached_usage['remaining'] ) || ! is_numeric( $cached_usage['remaining'] ) || $cached_usage['remaining'] <= 0 ) {
			return null;
		}

		// Fresh API check
		$fresh_usage = $this->get_usage_callback ? call_user_func( $this->get_usage_callback ) : null;

		if ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) && isset( $fresh_usage['remaining'] ) && is_numeric( $fresh_usage['remaining'] ) && $fresh_usage['remaining'] > 0 ) {
			// Fresh check shows credits available - backend was incorrect
			\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Backend reported quota limit but cache and fresh API check show credits available',
					array(
						'cached_remaining' => $cached_usage['remaining'],
						'api_remaining'    => $fresh_usage['remaining'],
					),
					'api'
				);
			}

			return new \WP_Error(
				'quota_check_mismatch',
				__( 'Backend reported quota limit, but credits appear available. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' ),
				array(
					'usage'       => $fresh_usage,
					'retry_after' => 3,
					'error_code'  => 'out_of_credits',
				)
			);
		} elseif ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) && isset( $fresh_usage['remaining'] ) && is_numeric( $fresh_usage['remaining'] ) && $fresh_usage['remaining'] === 0 ) {
			// Fresh check confirms 0 credits - backend was correct
			\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );
		}

		return null;
	}

	/**
	 * Log API event
	 */
	private function log_api_event( $level, $message, $context = array() ) {
		if ( $this->log_api_event_callback ) {
			call_user_func( $this->log_api_event_callback, $level, $message, $context );
		} elseif ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log( $level, $message, $context, 'api' );
		}
	}
}
