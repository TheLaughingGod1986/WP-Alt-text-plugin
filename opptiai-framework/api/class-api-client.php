<?php
/**
 * OpptiAI Framework - Base API Client
 *
 * Handles HTTP requests, retries, error handling, and logging for OpptiAI services
 *
 * @package OpptiAI\Framework\API
 */

namespace OpptiAI\Framework\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OpptiAI\Framework\Auth\Auth;

class API_Client {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	protected $api_url;

	/**
	 * Auth handler instance
	 *
	 * @var Auth
	 */
	protected $auth;

	/**
	 * Debug log class name (if available)
	 *
	 * @var string|null
	 */
	protected $log_class = null;

	/**
	 * Constructor
	 *
	 * @param string $api_url Base API URL
	 * @param Auth   $auth    Auth handler instance
	 */
	public function __construct( $api_url, $auth = null ) {
		$this->api_url = $this->determine_api_url( $api_url );
		$this->auth    = $auth ? $auth : new Auth();

		// Check for debug log class
		if ( class_exists( 'AltText_AI_Debug_Log' ) ) {
			$this->log_class = 'AltText_AI_Debug_Log';
		}
	}

	/**
	 * Determine API URL based on environment
	 *
	 * @param string $default_url Default production URL
	 * @return string Final API URL
	 */
	protected function determine_api_url( $default_url ) {
		// Allow override via wp-config.php
		if ( defined( 'ALTTEXT_AI_API_URL' ) ) {
			return ALTTEXT_AI_API_URL;
		}

		// Local development mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
			return 'http://host.docker.internal:3001';
		}

		return $default_url;
	}

	/**
	 * Make authenticated API request
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method   HTTP method (GET, POST, PUT, DELETE, etc.)
	 * @param mixed  $data     Request data
	 * @param int    $timeout  Request timeout in seconds
	 * @return array|\WP_Error Response data or error
	 */
	protected function make_request( $endpoint, $method = 'GET', $data = null, $timeout = null ) {
		$url     = trailingslashit( $this->api_url ) . ltrim( $endpoint, '/' );
		$headers = $this->auth->get_auth_headers();

		// Use longer timeout for generation requests
		if ( null === $timeout ) {
			$is_generate_endpoint = ( false !== strpos( $endpoint, '/api/generate' ) ) || ( false !== strpos( $endpoint, 'api/generate' ) );
			$timeout              = $is_generate_endpoint ? 90 : 30;
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => $timeout,
		);

		if ( $data && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$this->log_api_event(
			'debug',
			'API request started',
			array(
				'endpoint' => $endpoint,
				'method'   => $method,
			)
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $this->handle_request_error( $response, $endpoint, $method );
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

		// Handle HTTP errors
		if ( $status_code >= 400 ) {
			return $this->handle_http_error( $status_code, $body, $data, $endpoint, $method );
		}

		return $data;
	}

	/**
	 * Make request with automatic retry logic
	 *
	 * @param string $endpoint     API endpoint
	 * @param string $method       HTTP method
	 * @param mixed  $data         Request data
	 * @param int    $max_attempts Maximum retry attempts
	 * @return array|\WP_Error Response data or error
	 */
	protected function request_with_retry( $endpoint, $method = 'GET', $data = null, $max_attempts = 3 ) {
		$attempt = 0;

		while ( $attempt < $max_attempts ) {
			++$attempt;

			$response = $this->make_request( $endpoint, $method, $data );

			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			// Check if we should retry
			if ( $attempt < $max_attempts && $this->should_retry_api_error( $response ) ) {
				// Exponential backoff: 1s, 2s, 4s
				$wait_time = pow( 2, $attempt - 1 );
				sleep( $wait_time );
				continue;
			}

			// Don't retry, return error
			return $response;
		}

		return new \WP_Error(
			'max_retries_exceeded',
			__( 'Maximum retry attempts exceeded. Please try again later.', 'wp-alt-text-plugin' )
		);
	}

	/**
	 * Check if error should trigger retry
	 *
	 * @param \WP_Error $error Error object
	 * @return bool True if should retry
	 */
	protected function should_retry_api_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$code    = $error->get_error_code();
		$message = $error->get_error_message();

		// Retry on timeout or connection errors
		$retry_codes = array( 'api_timeout', 'api_unreachable', 'http_request_failed' );
		if ( in_array( $code, $retry_codes, true ) ) {
			return true;
		}

		// Retry on 5xx server errors
		if ( 'server_error' === $code ) {
			return true;
		}

		// Retry on rate limiting (429)
		if ( false !== strpos( $message, 'rate limit' ) || false !== strpos( $message, '429' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handle request errors (WP_Error from wp_remote_request)
	 *
	 * @param \WP_Error $error    Error object
	 * @param string    $endpoint Endpoint
	 * @param string    $method   HTTP method
	 * @return \WP_Error
	 */
	protected function handle_request_error( $error, $endpoint, $method ) {
		$error_message = $error->get_error_message();

		$this->log_api_event(
			'error',
			'API request failed',
			array(
				'endpoint' => $endpoint,
				'method'   => $method,
				'error'    => $error_message,
			)
		);

		// Timeout errors
		if ( false !== strpos( $error_message, 'timeout' ) ) {
			$is_generate_endpoint = ( false !== strpos( $endpoint, '/api/generate' ) ) || ( false !== strpos( $endpoint, 'api/generate' ) );
			if ( $is_generate_endpoint ) {
				return new \WP_Error(
					'api_timeout',
					__( 'The image generation is taking longer than expected. This may happen with large images or during high server load. Please try again.', 'wp-alt-text-plugin' )
				);
			}
			return new \WP_Error(
				'api_timeout',
				__( 'The server is taking too long to respond. Please try again in a few minutes.', 'wp-alt-text-plugin' )
			);
		}

		// Connection errors
		if ( false !== strpos( $error_message, 'could not resolve' ) ) {
			return new \WP_Error(
				'api_unreachable',
				__( 'Unable to reach authentication server. Please check your internet connection and try again.', 'wp-alt-text-plugin' )
			);
		}

		return new \WP_Error( 'api_error', $error_message );
	}

	/**
	 * Handle HTTP error responses (4xx, 5xx)
	 *
	 * @param int    $status_code HTTP status code
	 * @param string $body        Response body
	 * @param mixed  $data        Decoded data
	 * @param string $endpoint    Endpoint
	 * @param string $method      HTTP method
	 * @return \WP_Error
	 */
	protected function handle_http_error( $status_code, $body, $data, $endpoint, $method ) {
		// 404 Not Found
		if ( 404 === $status_code ) {
			// Check if it's an HTML error page
			if ( false !== strpos( $body, '<html' ) || false !== strpos( $body, 'Cannot POST' ) || false !== strpos( $body, 'Cannot GET' ) ) {
				$error_message = __( 'This feature is not yet available. Please contact support for assistance or try again later.', 'wp-alt-text-plugin' );

				// Endpoint-specific messages
				if ( false !== strpos( $endpoint, '/auth/forgot-password' ) || false !== strpos( $endpoint, '/auth/reset-password' ) ) {
					$error_message = __( 'Password reset functionality is currently being set up on our backend. Please contact support for assistance or try again later.', 'wp-alt-text-plugin' );
				} elseif ( false !== strpos( $endpoint, '/licenses/sites' ) || false !== strpos( $endpoint, '/api/licenses/sites' ) ) {
					$error_message = __( 'License site usage tracking is currently being set up on our backend. Please contact support for assistance or try again later.', 'wp-alt-text-plugin' );
				}

				return new \WP_Error( 'endpoint_not_found', $error_message );
			}

			return new \WP_Error(
				'not_found',
				$data['error'] ?? $data['message'] ?? __( 'The requested resource was not found.', 'wp-alt-text-plugin' )
			);
		}

		// 5xx Server Errors
		if ( $status_code >= 500 ) {
			return $this->handle_server_error( $status_code, $body, $data, $endpoint, $method );
		}

		// 4xx Client Errors
		$error_code    = 'http_error_' . $status_code;
		$error_message = $data['message'] ?? $data['error'] ?? sprintf(
			/* translators: %d: HTTP status code */
			__( 'API request failed with status %d', 'wp-alt-text-plugin' ),
			$status_code
		);

		return new \WP_Error( $error_code, $error_message, array( 'status' => $status_code ) );
	}

	/**
	 * Handle 5xx server errors
	 *
	 * @param int    $status_code HTTP status code
	 * @param string $body        Response body
	 * @param mixed  $data        Decoded data
	 * @param string $endpoint    Endpoint
	 * @param string $method      HTTP method
	 * @return \WP_Error
	 */
	protected function handle_server_error( $status_code, $body, $data, $endpoint, $method ) {
		$error_details = '';
		if ( is_array( $data ) && isset( $data['error'] ) ) {
			$error_details = $data['error'];
		} elseif ( is_array( $data ) && isset( $data['message'] ) ) {
			$error_details = $data['message'];
		} elseif ( ! empty( $body ) && strlen( $body ) < 500 ) {
			$error_details = $body;
		}

		$this->log_api_event(
			'error',
			'API server error',
			array(
				'endpoint'      => $endpoint,
				'method'        => $method,
				'status'        => $status_code,
				'error_details' => $error_details,
				'body_preview'  => substr( $body, 0, 200 ),
			)
		);

		$error_message = __( 'The server encountered an error processing your request. Please try again in a few minutes.', 'wp-alt-text-plugin' );

		// Endpoint-specific error messages
		$is_generate_endpoint = ( false !== strpos( $endpoint, '/api/generate' ) ) || ( false !== strpos( $endpoint, 'api/generate' ) );
		if ( $is_generate_endpoint ) {
			if ( false !== strpos( strtolower( $error_details ), 'incorrect api key' ) ||
				 false !== strpos( strtolower( $error_details ), 'invalid api key' ) ) {
				$error_message = __( 'The image generation service is temporarily unavailable due to a backend configuration issue. Please contact support.', 'wp-alt-text-plugin' );
			} else {
				$error_message = __( 'The image generation service is temporarily unavailable. Please try again in a few minutes.', 'wp-alt-text-plugin' );
			}
		} elseif ( false !== strpos( $endpoint, '/auth/' ) ) {
			$error_message = __( 'The authentication server is temporarily unavailable. Please try again in a few minutes.', 'wp-alt-text-plugin' );
		}

		return new \WP_Error( 'server_error', $error_message, array( 'status' => $status_code ) );
	}

	/**
	 * Sanitize context data before logging
	 *
	 * @param mixed $context Context data
	 * @return mixed Sanitized context
	 */
	protected function sanitize_log_context( $context ) {
		if ( ! is_array( $context ) ) {
			return $context;
		}

		$sanitized      = array();
		$sensitive_keys = array( 'url', 'token', 'api_key', 'password', 'secret', 'authorization', 'auth', 'bearer' );

		foreach ( $context as $key => $value ) {
			$key_lower = strtolower( $key );

			// Skip sensitive keys
			foreach ( $sensitive_keys as $sensitive ) {
				if ( false !== strpos( $key_lower, $sensitive ) ) {
					$sanitized[ $key ] = '[REDACTED]';
					continue 2;
				}
			}

			// Sanitize URLs
			if ( 'url' === $key && is_string( $value ) ) {
				$parsed = parse_url( $value );
				if ( isset( $parsed['path'] ) ) {
					$sanitized[ $key ] = $parsed['path'] . ( isset( $parsed['query'] ) ? '?[QUERY_PARAMS]' : '' );
				} else {
					$sanitized[ $key ] = '[REDACTED_URL]';
				}
			} elseif ( 'endpoint' === $key && is_string( $value ) ) {
				// Remove query parameters
				$sanitized[ $key ] = strtok( $value, '?' );
			} elseif ( 'error' === $key && is_string( $value ) ) {
				// Remove sensitive data from error messages
				$sanitized[ $key ] = preg_replace(
					array(
						'/https?:\/\/[^\s]+/i',
						'/Bearer\s+[A-Za-z0-9\-_]+/i',
						'/token[=:]\s*[A-Za-z0-9\-_]+/i',
						'/api[_-]?key[=:]\s*[A-Za-z0-9\-_]+/i',
					),
					'[REDACTED]',
					$value
				);
			} elseif ( is_array( $value ) ) {
				// Recursively sanitize nested arrays
				$sanitized[ $key ] = $this->sanitize_log_context( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Log API event
	 *
	 * @param string $level   Log level (debug, info, warning, error)
	 * @param string $message Log message
	 * @param array  $context Context data
	 * @return void
	 */
	protected function log_api_event( $level, $message, $context = array() ) {
		if ( $this->log_class && class_exists( $this->log_class ) ) {
			$sanitized_context = $this->sanitize_log_context( $context );
			call_user_func( array( $this->log_class, 'log' ), $level, $message, $sanitized_context, 'api' );
		}
	}

	/**
	 * Check if client is authenticated
	 *
	 * @return bool True if authenticated
	 */
	public function is_authenticated() {
		return $this->auth->is_authenticated();
	}

	/**
	 * Get auth handler
	 *
	 * @return Auth
	 */
	public function get_auth() {
		return $this->auth;
	}
}
