<?php
/**
 * Generation Client
 *
 * Handles alt text generation and review operations via the backend API.
 * Manages authentication, license key handling, and retry logic for generation requests.
 *
 * @package BeepBeepAI\AltTextGenerator\API
 * @since 4.2.0
 */

namespace BeepBeepAI\AltTextGenerator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Class Generation_Client
 *
 * Handles communication with the backend API for alt text generation.
 * Supports both JWT token and license key authentication methods.
 *
 * @since 4.2.0
 */
class Generation_Client {

	/**
	 * Request handler instance for making API calls.
	 *
	 * @var API_Request_Handler
	 */
	private $request_handler;

	/**
	 * Error handler instance for processing API errors.
	 *
	 * @var API_Error_Handler|null
	 */
	private $error_handler;

	/**
	 * Callback to check if user has active license.
	 *
	 * @var callable|null
	 */
	private $has_active_license_callback;

	/**
	 * Callback to get JWT token.
	 *
	 * @var callable|null
	 */
	private $get_token_callback;

	/**
	 * Callback to clear JWT token.
	 *
	 * @var callable|null
	 */
	private $clear_token_callback;

	/**
	 * Callback to check if user is authenticated.
	 *
	 * @var callable|null
	 */
	private $is_authenticated_callback;

	/**
	 * Callback to auto-attach license.
	 *
	 * @var callable|null
	 */
	private $auto_attach_license_callback;

	/**
	 * Callback to get license key.
	 *
	 * @var callable|null
	 */
	private $get_license_key_callback;

	/**
	 * Callback to get license data.
	 *
	 * @var callable|null
	 */
	private $get_license_data_callback;

	/**
	 * Callback to set license key.
	 *
	 * @var callable|null
	 */
	private $set_license_key_callback;

	/**
	 * Callback to get usage information.
	 *
	 * @var callable|null
	 */
	private $get_usage_callback;

	/**
	 * Callback to get site identifier.
	 *
	 * @var callable|null
	 */
	private $get_site_id_callback;

	/**
	 * Callback to get user information.
	 *
	 * @var callable|null
	 */
	private $get_user_info_callback;

	/**
	 * Constructor.
	 *
	 * @since 4.2.0
	 *
	 * @param API_Request_Handler    $request_handler Request handler instance.
	 * @param API_Error_Handler|null $error_handler  Optional error handler instance.
	 * @param array                  $callbacks       Array of callback functions for various operations.
	 */
	public function __construct( $request_handler, $error_handler = null, $callbacks = array() ) {
		$this->request_handler              = $request_handler;
		$this->error_handler                = $error_handler;
		$this->has_active_license_callback  = $callbacks['has_active_license'] ?? null;
		$this->get_token_callback           = $callbacks['get_token'] ?? null;
		$this->clear_token_callback         = $callbacks['clear_token'] ?? null;
		$this->is_authenticated_callback    = $callbacks['is_authenticated'] ?? null;
		$this->auto_attach_license_callback = $callbacks['auto_attach_license'] ?? null;
		$this->get_license_key_callback     = $callbacks['get_license_key'] ?? null;
		$this->get_license_data_callback    = $callbacks['get_license_data'] ?? null;
		$this->set_license_key_callback     = $callbacks['set_license_key'] ?? null;
		$this->get_usage_callback           = $callbacks['get_usage'] ?? null;
		$this->get_site_id_callback         = $callbacks['get_site_id'] ?? null;
		$this->get_user_info_callback       = $callbacks['get_user_info'] ?? null;
	}

	/**
	 * Generate alt text for an image via the backend API.
	 *
	 * Handles authentication, license key management, image preparation,
	 * API request, and response processing. Supports retry logic for
	 * quota mismatches and other transient errors.
	 *
	 * @since 4.2.0
	 *
	 * @param int   $image_id   WordPress attachment ID.
	 * @param array $context    Optional context array for generation (e.g., 'manual', 'auto', 'rest').
	 * @param bool  $regenerate Whether to regenerate existing alt text.
	 * @return array|\WP_Error On success, returns array with 'alt_text' key. On failure, returns WP_Error.
	 */
	public function generate_alt_text( $image_id, $context = array(), $regenerate = false ) {
		$has_license = $this->has_active_license_callback ? call_user_func( $this->has_active_license_callback ) : false;

		// Validate authentication if no license
		if ( ! $has_license ) {
			$token = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';
			if ( ! empty( $token ) && ! defined( 'WP_LOCAL_DEV' ) ) {
				$last_check = get_transient( 'bbai_token_last_check' );
				if ( $last_check === false ) {
					$user_info = $this->get_user_info_callback ? call_user_func( $this->get_user_info_callback ) : null;
					if ( is_wp_error( $user_info ) ) {
						$error_code    = $user_info->get_error_code();
						$error_message = strtolower( $user_info->get_error_message() );

						// Ensure error_message is a string before using strpos (PHP 8.1+ compatibility)
						$error_message_str = is_string( $error_message ) ? $error_message : '';
						if ( $error_code === 'auth_required' ||
							$error_code === 'user_not_found' ||
							( ! empty( $error_message_str ) && strpos( $error_message_str, 'user not found' ) !== false ) ||
							( ! empty( $error_message_str ) && strpos( $error_message_str, 'session expired' ) !== false ) ||
							( ! empty( $error_message_str ) && strpos( $error_message_str, 'unauthorized' ) !== false ) ) {
							if ( $this->clear_token_callback ) {
								call_user_func( $this->clear_token_callback );
							}
							delete_transient( 'bbai_token_last_check' );
							return new \WP_Error(
								'auth_required',
								__( 'Your session has expired. Please log in again.', 'beepbeep-ai-alt-text-generator' ),
								array( 'requires_auth' => true )
							);
						}
					} else {
						set_transient( 'bbai_token_last_check', time(), 5 * MINUTE_IN_SECONDS );
					}
				}
			}

			// Try auto-attach if authenticated but no license
			if ( $this->is_authenticated_callback && call_user_func( $this->is_authenticated_callback ) && ! $has_license ) {
				$last_auto_attach = get_transient( 'bbai_last_auto_attach_attempt' );
				$should_retry     = $last_auto_attach === false || ( time() - $last_auto_attach ) > 300;

				if ( $should_retry && $this->auto_attach_license_callback ) {
					if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'info',
							'Attempting auto-attach license before generation (no license found)',
							array(
								'image_id' => $image_id,
							),
							'licensing'
						);
					}

					$auto_attach_result = call_user_func( $this->auto_attach_license_callback );
					set_transient( 'bbai_last_auto_attach_attempt', time(), 300 );

					if ( ! is_wp_error( $auto_attach_result ) ) {
						if ( $this->has_active_license_callback && call_user_func( $this->has_active_license_callback ) ) {
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'info',
									'Auto-attach succeeded during generation, license now active',
									array(
										'plan' => $auto_attach_result['license']['plan'] ?? 'free',
									),
									'licensing'
								);
							}
						}
					} else {
						$error_data      = $auto_attach_result->get_error_data();
						$is_schema_error = isset( $error_data['is_schema_error'] ) && $error_data['is_schema_error'];

						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							\BeepBeepAI\AltTextGenerator\Debug_Log::log(
								'warning',
								'Auto-attach failed during generation (non-blocking)',
								array(
									'error'           => $auto_attach_result->get_error_message(),
									'is_schema_error' => $is_schema_error,
								),
								'licensing'
							);
						}
					}
				}
			}
		}

		// Validate site fingerprint
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
		$fingerprint_check = \BeepBeepAI\AltTextGenerator\Site_Fingerprint::check_on_credit_operation( false );
		if ( is_wp_error( $fingerprint_check ) ) {
			return $fingerprint_check;
		}

		$endpoint = 'api/generate';

		// Check cache age before generation - refresh if cache is > 2 minutes old
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );
		$cache_age    = 0;

		if ( is_array( $cached_usage ) && isset( $cached_usage['_cache_timestamp'] ) ) {
			$cache_age = time() - intval( $cached_usage['_cache_timestamp'] );
		} else {
			// If cache doesn't have timestamp, check if transient exists
			// If it exists but no timestamp, we can't determine age, so refresh to be safe
			$cache_data = get_transient( 'bbai_usage_cache' );
			if ( $cache_data !== false && is_array( $cache_data ) ) {
				// Cache exists but no timestamp - refresh to ensure accuracy
				$cache_age = 121; // Force refresh
			}
		}

		// Refresh cache if it's > 2 minutes old (120 seconds)
		if ( $cache_age > 120 ) {
			if ( $this->get_usage_callback ) {
				$fresh_usage = call_user_func( $this->get_usage_callback );
				if ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) ) {
					\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );
				}
			}
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'warning',
				'generate_alt_text called',
				array(
					'image_id'          => $image_id,
					'regenerate'        => $regenerate,
					'endpoint'          => $endpoint,
					'cache_age_seconds' => $cache_age,
				),
				'api'
			);
		}

		// Prepare image data
		$image_url        = wp_get_attachment_url( $image_id );
		$title            = get_the_title( $image_id );
		$caption          = wp_get_attachment_caption( $image_id );
		$parsed_image_url = $image_url ? wp_parse_url( $image_url ) : null;
		$filename         = $parsed_image_url && isset( $parsed_image_url['path'] ) ? wp_basename( $parsed_image_url['path'] ) : '';

		if ( $regenerate && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			$file_path   = get_attached_file( $image_id );
			$file_exists = $file_path && file_exists( $file_path );
			$file_hash   = $file_exists && function_exists( 'md5_file' ) ? md5_file( $file_path ) : 'unknown';

			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'info',
				'Regenerating alt text for image',
				array(
					'image_id'    => $image_id,
					'image_url'   => $image_url,
					'file_path'   => $file_path,
					'file_exists' => $file_exists,
					'file_hash'   => substr( $file_hash, 0, 8 ) . '...',
					'filename'    => $filename,
					'title'       => $title,
				),
				'api'
			);
		}

		$image_payload = $this->prepare_image_payload( $image_id, $image_url, $title, $caption, $filename );

		// Check for image preparation errors
		if ( isset( $image_payload['_error'] ) && $image_payload['_error'] === 'image_too_large' ) {
			return new \WP_Error(
				'image_too_large',
				$image_payload['_error_message'] ?? __( 'Image file is too large.', 'beepbeep-ai-alt-text-generator' ),
				array( 'image_id' => $image_id )
			);
		}

		if ( isset( $image_payload['_error'] ) && $image_payload['_error'] === 'missing_image_data' ) {
			return new \WP_Error(
				'missing_image_data',
				$image_payload['_error_message'] ?? __( 'Image data is missing. Cannot generate alt text.', 'beepbeep-ai-alt-text-generator' ),
				array( 'image_id' => $image_id )
			);
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			$log_level = $regenerate ? 'warning' : 'info';
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				$log_level,
				'Sending image to backend API',
				array(
					'image_id'            => $image_id,
					'regenerate'          => $regenerate,
					'has_image_url'       => ! empty( $image_payload['image_url'] ),
					'has_image_base64'    => ! empty( $image_payload['image_base64'] ),
					'image_url_preview'   => ! empty( $image_payload['image_url'] ) ? substr( $image_payload['image_url'], 0, 100 ) . '...' : 'none',
					'base64_length'       => ! empty( $image_payload['image_base64'] ) ? strlen( $image_payload['image_base64'] ) : 0,
					'payload_keys'        => array_keys( $image_payload ),
					'image_id_in_payload' => ! empty( $image_payload['image_id'] ) ? $image_payload['image_id'] : 'missing',
				),
				'api'
			);
		}

		if ( empty( $image_payload['image_url'] ) && empty( $image_payload['image_base64'] ) ) {
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'Image payload missing both image_url and image_base64',
					array(
						'image_id'        => $image_id,
						'payload_keys'    => array_keys( $image_payload ),
						'image_url_input' => $image_url,
					),
					'api'
				);
			}
		}

		// Normalize image_id in payload
		if ( isset( $image_payload['image_id'] ) && (string) $image_payload['image_id'] !== (string) $image_id ) {
			$image_payload['image_id'] = (string) $image_id;
		}

		if ( ! isset( $image_payload['attachment_id'] ) || (string) $image_payload['attachment_id'] !== (string) $image_id ) {
			$image_payload['attachment_id'] = (string) $image_id;
		}

		// Get license key and site hash
		$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
		$site_hash   = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';

		// Try to retrieve license key if missing
		// This ensures the key is available before building request headers
		if ( empty( $license_key ) && $has_license ) {
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Active license but no key found before generation - attempting retrieval',
					array(
						'has_active_license' => true,
						'image_id'           => $image_id,
					),
					'licensing'
				);
			}

			$license_data = $this->get_license_data_callback ? call_user_func( $this->get_license_data_callback ) : null;
			if ( ! empty( $license_data ) ) {
				if ( isset( $license_data['licenseKey'] ) && ! empty( $license_data['licenseKey'] ) ) {
					$license_key = $license_data['licenseKey'];
					if ( $this->set_license_key_callback ) {
						call_user_func( $this->set_license_key_callback, $license_key );
					}
				} elseif ( isset( $license_data['site']['licenseKey'] ) && ! empty( $license_data['site']['licenseKey'] ) ) {
					$license_key = $license_data['site']['licenseKey'];
					if ( $this->set_license_key_callback ) {
						call_user_func( $this->set_license_key_callback, $license_key );
					}
				}
			}

			if ( empty( $license_key ) && $this->get_usage_callback ) {
				$usage_response = call_user_func( $this->get_usage_callback );
				if ( ! is_wp_error( $usage_response ) ) {
					$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
				}
			}

			if ( empty( $license_key ) && $this->auto_attach_license_callback ) {
				$auto_attach_result = call_user_func( $this->auto_attach_license_callback );
				if ( ! is_wp_error( $auto_attach_result ) ) {
					$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
				}
			}

			if ( empty( $license_key ) && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'Active license detected but license key still not found after all attempts',
					array(
						'has_active_license' => true,
						'has_license_data'   => ! empty( $license_data ),
						'image_id'           => $image_id,
					),
					'licensing'
				);
			}
		}

		// Final refresh of license key to ensure it's available for headers
		// This handles cases where the key was just stored but callback hasn't refreshed
		if ( ! empty( $license_key ) && $this->set_license_key_callback ) {
			// Ensure the key is stored and will be available when headers are built
			call_user_func( $this->set_license_key_callback, $license_key );
			// Refresh the value from callback to ensure consistency
			$refreshed_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
			if ( ! empty( $refreshed_key ) && $refreshed_key !== $license_key ) {
				$license_key = $refreshed_key;
			}
		}

		$body = array(
			'image_data'    => $image_payload,
			'context'       => $context,
			'regenerate'    => $regenerate ? true : false,
			'service'       => 'alttext-ai',
			'timestamp'     => time(),
			'image_id'      => (string) $image_id,
			'attachment_id' => (string) $image_id,
		);

		if ( ! empty( $license_key ) ) {
			$body['licenseKey'] = $license_key;
		}
		if ( ! empty( $site_hash ) ) {
			$body['siteHash'] = $site_hash;
			$body['site_id']  = $site_hash;
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'info',
				'API Request Payload (ready to send to backend)',
				array(
					'image_id_param'           => $image_id,
					'root_image_id_in_body'    => (string) $image_id,
					'image_data.image_id'      => $image_payload['image_id'] ?? 'missing',
					'image_data.attachment_id' => $image_payload['attachment_id'] ?? 'missing',
					'regenerate_flag'          => $regenerate,
					'has_image_url'            => ! empty( $image_payload['image_url'] ),
					'has_license_key'          => ! empty( $license_key ),
					'license_key_preview'      => ! empty( $license_key ) ? substr( $license_key, 0, 20 ) . '...' : 'none',
					'has_site_hash'            => ! empty( $site_hash ),
					'site_hash'                => $site_hash ?? 'none',
					'has_active_license'       => $has_license,
					'license_key_in_body'      => ! empty( $body['licenseKey'] ),
					'site_hash_in_body'        => ! empty( $body['siteHash'] ),
				),
				'api'
			);
		}

		$extra_headers = array(
			'X-Image-ID'      => (string) $image_id,
			'X-Attachment-ID' => (string) $image_id,
		);

		// CRITICAL: Ensure license key is explicitly in headers for generate endpoint
		// This prevents issues where the backend requires it in headers vs body
		if ( ! empty( $license_key ) ) {
			$extra_headers['X-License-Key'] = $license_key;

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'info',
					'License key explicitly added to generate request headers',
					array(
						'license_key_preview'        => substr( $license_key, 0, 20 ) . '...',
						'has_license_key_in_body'    => ! empty( $body['licenseKey'] ),
						'has_license_key_in_headers' => true,
					),
					'api'
				);
			}
		}

		$response = $this->request_handler->request_with_retry( $endpoint, 'POST', $body, 3, true, $extra_headers );

		if ( is_wp_error( $response ) ) {
			// Handle quota_check_mismatch retry
			if ( $response->get_error_code() === 'quota_check_mismatch' ) {
				$retry_after = $response->get_error_data()['retry_after'] ?? 3;

				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'info',
						'Quota mismatch detected - clearing cache and waiting before retry',
						array(
							'retry_after' => $retry_after,
							'image_id'    => $image_id,
						),
						'api'
					);
				}

				// CRITICAL: Clear usage cache to force fresh check before retry
				// This ensures backend and frontend are synced
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Usage_Tracker' ) ) {
					\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
				}

				sleep( $retry_after );

				// Refresh license key and site hash before retry (in case they changed)
				$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
				$site_hash   = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';

				// Ensure license key is still in headers for retry
				if ( ! empty( $license_key ) && ! isset( $extra_headers['X-License-Key'] ) ) {
					$extra_headers['X-License-Key'] = $license_key;
				}

				// Update body with fresh license key if needed
				if ( ! empty( $license_key ) && ( ! isset( $body['licenseKey'] ) || empty( $body['licenseKey'] ) ) ) {
					$body['licenseKey'] = $license_key;
				}
				if ( ! empty( $site_hash ) && ( ! isset( $body['siteHash'] ) || empty( $body['siteHash'] ) ) ) {
					$body['siteHash'] = $site_hash;
					$body['site_id']  = $site_hash;
				}

				$retry_response = $this->request_handler->request_with_retry( $endpoint, 'POST', $body, 1, true, $extra_headers );

				if ( ! is_wp_error( $retry_response ) ) {
					if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'info',
							'Quota mismatch retry succeeded - backend synced',
							array(
								'image_id' => $image_id,
							),
							'api'
						);
					}
					return $retry_response;
				}

				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'warning',
						'Quota mismatch retry also failed',
						array(
							'image_id'         => $image_id,
							'retry_error'      => $retry_response->get_error_message(),
							'retry_error_code' => $retry_response->get_error_code(),
						),
						'api'
					);
				}
			}

			return $response;
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'debug',
				'Generation API response',
				array(
					'status_code' => $response['status_code'],
					'success'     => $response['success'],
					'has_data'    => isset( $response['data'] ),
					'data_keys'   => isset( $response['data'] ) && is_array( $response['data'] ) ? array_keys( $response['data'] ) : 'not array',
				),
				'api'
			);
		}

		// Handle 429 rate limit errors
		if ( $response['status_code'] === 429 && $this->error_handler ) {
			$rate_limit_error = $this->error_handler->handle_rate_limit_error( $response );
			if ( is_wp_error( $rate_limit_error ) ) {
				return $rate_limit_error;
			}
		}

		if ( ! $response['success'] ) {
			$error_data    = $response['data'] ?? array();
			$error_message = $error_data['message'] ?? $error_data['error'] ?? __( 'Failed to generate alt text', 'beepbeep-ai-alt-text-generator' );
			$error_code    = $error_data['code'] ?? 'api_error';

			$error_message_lower = strtolower( $error_message . ' ' . ( $error_data['error'] ?? '' ) );
			$status_code_check   = isset( $response['status_code'] ) ? intval( $response['status_code'] ) : 0;

			if ( ( strpos( $error_message_lower, 'user not found' ) !== false ||
				strpos( $error_message_lower, 'user does not exist' ) !== false ||
				( $status_code_check === 401 && strpos( $error_message_lower, 'unauthorized' ) !== false ) ) &&
				$status_code_check < 500 ) {

				if ( $this->clear_token_callback ) {
					call_user_func( $this->clear_token_callback );
				}
				delete_transient( 'bbai_token_last_check' );
				return new \WP_Error(
					'auth_required',
					__( 'Your session has expired or your account is no longer available. Please log in again.', 'beepbeep-ai-alt-text-generator' ),
					array(
						'requires_auth' => true,
						'status_code'   => $status_code_check,
						'code'          => 'user_not_found',
					)
				);
			}

			if ( $response['status_code'] === 413 ) {
				$error_message = __( 'Image file is too large. Please compress or resize the image before generating alt text.', 'beepbeep-ai-alt-text-generator' );
				$error_code    = 'payload_too_large';
			}

			$backend_error_lower = strtolower( $error_message . ' ' . ( $error_data['error'] ?? '' ) );
			if ( strpos( $backend_error_lower, 'incorrect api key' ) !== false ||
				strpos( $backend_error_lower, 'invalid api key' ) !== false ||
				strpos( $backend_error_lower, 'api key provided' ) !== false ||
				$error_code === 'GENERATION_ERROR' ) {
				$error_message = __( 'The backend service is experiencing a configuration issue. This is a temporary backend problem that needs to be fixed on the server side. Please try again in a few minutes or contact support if the issue persists.', 'beepbeep-ai-alt-text-generator' );
				$error_code    = 'backend_config_error';
			}

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'API generation failed',
					array(
						'image_id'      => $image_id,
						'status_code'   => $response['status_code'],
						'error_code'    => $error_code,
						'error_message' => $error_message,
						'backend_error' => $error_data['error'] ?? $error_data['message'] ?? 'Unknown error',
						'backend_code'  => $error_data['code'] ?? 'unknown',
					),
					'api'
				);
			}

			return new \WP_Error(
				'api_error',
				$error_message,
				array(
					'code'          => $error_code,
					'status_code'   => $response['status_code'],
					'image_id'      => $image_id,
					'api_response'  => $error_data,
					'backend_error' => $error_data['error'] ?? $error_data['message'] ?? null,
				)
			);
		}

		// Refresh usage cache after successful generation
		if ( $this->get_usage_callback ) {
			$fresh_usage = call_user_func( $this->get_usage_callback );
			if ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );
			}
		}

		return $response['data'];
	}

	/**
	 * Review existing alt text
	 */
	public function review_alt_text( $image_id, $alt_text, $context = array() ) {
		$endpoint = 'api/review';

		$image_url        = wp_get_attachment_url( $image_id );
		$title            = get_the_title( $image_id );
		$caption          = wp_get_attachment_caption( $image_id );
		$parsed_image_url = $image_url ? wp_parse_url( $image_url ) : null;
		$filename         = $parsed_image_url && isset( $parsed_image_url['path'] ) ? wp_basename( $parsed_image_url['path'] ) : '';

		$body = array(
			'alt_text'   => $alt_text,
			'image_data' => $this->prepare_image_payload( $image_id, $image_url, $title, $caption, $filename ),
			'context'    => $context,
		);

		$response = $this->request_handler->request_with_retry( $endpoint, 'POST', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $response['success'] ) {
			$error_message = $response['data']['message'] ?? $response['data']['error'] ?? __( 'Failed to review alt text', 'beepbeep-ai-alt-text-generator' );
			return new \WP_Error(
				'api_error',
				$error_message,
				array( 'code' => $response['data']['code'] ?? 'api_error' )
			);
		}

		return $response['data'];
	}

	/**
	 * Prepare image payload for API
	 */
	public function prepare_image_payload( $image_id, $image_url, $title, $caption, $filename ) {
		$payload = array(
			'image_id'      => (string) $image_id,
			'attachment_id' => (string) $image_id,
			'title'         => $title,
			'caption'       => $caption,
			'filename'      => $filename,
		);

		// Get image dimensions
		$metadata = wp_get_attachment_metadata( $image_id );
		if ( $metadata && isset( $metadata['width'], $metadata['height'] ) ) {
			$payload['width']  = $metadata['width'];
			$payload['height'] = $metadata['height'];
		}

		if ( $image_url ) {
			$file_path = get_attached_file( $image_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size     = filesize( $file_path );
				$max_file_size = 512 * 1024; // 512KB

				$mime_type = get_post_mime_type( $image_id ) ?: 'image/jpeg';
				$metadata  = wp_get_attachment_metadata( $image_id );

				$should_resize = ( $file_size > $max_file_size ) ||
								( empty( $metadata ) || empty( $metadata['width'] ) || empty( $metadata['height'] ) );

				if ( $should_resize && function_exists( 'wp_get_image_editor' ) ) {
					$orig_width  = $metadata['width'] ?? 0;
					$orig_height = $metadata['height'] ?? 0;
					$max_size    = 800;

					if ( $orig_width > $max_size || $orig_height > $max_size ) {
						if ( $orig_width > $orig_height ) {
							$new_width  = $max_size;
							$new_height = intval( ( $orig_height / $orig_width ) * $max_size );
						} else {
							$new_height = $max_size;
							$new_width  = intval( ( $orig_width / $orig_height ) * $max_size );
						}

						$editor = wp_get_image_editor( $file_path );
						if ( ! is_wp_error( $editor ) ) {
							$editor->resize( $new_width, $new_height, false );

							if ( method_exists( $editor, 'set_quality' ) ) {
								$editor->set_quality( 85 );
							}

							$upload_dir    = wp_upload_dir();
							$temp_filename = 'beepbeepai-temp-' . $image_id . '-' . time() . '.jpg';
							$temp_path     = $upload_dir['path'] . '/' . $temp_filename;
							$saved         = $editor->save( $temp_path, 'image/jpeg' );

							if ( ! is_wp_error( $saved ) && isset( $saved['path'] ) ) {
								$resized_contents = file_get_contents( $saved['path'] );
								@unlink( $saved['path'] );
								if ( $resized_contents !== false ) {
									$base64 = base64_encode( $resized_contents );
									if ( strlen( $base64 ) <= 5.5 * 1024 * 1024 ) {
										$payload['image_base64'] = $base64;
										$payload['mime_type']    = 'image/jpeg';
									} else {
										$payload['image_url'] = $image_url;
									}
								} else {
									$payload['image_url'] = $image_url;
								}
							} else {
								$payload['image_url'] = $image_url;
							}
						} else {
							$payload['image_url'] = $image_url;
						}
					} else {
						$payload['image_url'] = $image_url;
					}
				} else {
					$payload['image_url'] = $image_url;
				}
			} else {
				$payload['image_url'] = $image_url;
			}
		} elseif ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'prepare_image_payload: No image_url provided',
					array(
						'image_id'        => $image_id,
						'image_url_param' => $image_url,
					),
					'api'
				);
		}

		// Try to include inline image data
		$file_path = $file_path ?? get_attached_file( $image_id );
		if ( empty( $payload['image_base64'] ) && $file_path && file_exists( $file_path ) ) {
			$file_size       = filesize( $file_path );
			$max_inline_size = 5.5 * 1024 * 1024; // ~5.5MB
			if ( $file_size > 0 && $file_size <= $max_inline_size ) {
				$contents = file_get_contents( $file_path );
				if ( $contents !== false ) {
					$base64 = base64_encode( $contents );
					if ( ! empty( $base64 ) && strlen( $base64 ) <= $max_inline_size * 1.4 ) {
						$mime_type               = $mime_type ?? get_post_mime_type( $image_id ) ?: 'image/jpeg';
						$payload['image_base64'] = $base64;
						$payload['mime_type']    = $mime_type;
					}
				}
			}
		}

		// Verify image data is included
		if ( empty( $payload['image_url'] ) && empty( $payload['image_base64'] ) ) {
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'prepare_image_payload: Payload missing image data',
					array(
						'image_id'        => $image_id,
						'payload_keys'    => array_keys( $payload ),
						'image_url_input' => $image_url,
					),
					'api'
				);
			}
			$payload['_error']         = 'missing_image_data';
			$payload['_error_message'] = 'Image URL or base64 data is required';
		}

		return $payload;
	}
}
