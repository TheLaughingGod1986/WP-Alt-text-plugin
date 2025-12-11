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

		// CRITICAL FIX: Backend requires JWT token even with license key
		// For site-based licensing, ensure we have a JWT token (create anonymous token if needed)
		if ( $has_license ) {
			$token = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';
			if ( empty( $token ) ) {
				// License exists but no JWT token - try to create an anonymous site-based token
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'warning',
						'Active license but no JWT token - backend requires both for generate endpoint',
						array(
							'has_license' => true,
							'has_token'   => false,
							'image_id'    => $image_id,
						),
						'auth'
					);
				}

				// Return user-friendly error explaining they need to log in
				return new \WP_Error(
					'jwt_token_required',
					__( 'Please log in to your BeepBeep AI account to use the alt text generator. Go to the plugin settings page and sign in with your account credentials.', 'beepbeep-ai-alt-text-generator' ),
					array(
						'requires_auth' => true,
						'code' => 'jwt_required_with_license',
					)
				);
			}
		}

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

		$endpoint = 'api/alt-text';

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
		if ( is_wp_error( $image_payload ) ) {
			return $image_payload;
		}

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

		if ( isset( $image_payload['_error'] ) && $image_payload['_error'] === 'invalid_image_size' ) {
			return new \WP_Error(
				'invalid_image_size',
				$image_payload['_error_message'] ?? __( 'Image size is invalid for encoding.', 'beepbeep-ai-alt-text-generator' ),
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

		// Backend validation: base64 payload must include dimensions
		if ( ! empty( $image_payload['image_base64'] ) && ( empty( $image_payload['width'] ) || empty( $image_payload['height'] ) ) ) {
			$decoded = base64_decode( $image_payload['image_base64'], true );
			if ( $decoded !== false ) {
				$image_size = getimagesizefromstring( $decoded );
				if ( $image_size ) {
					$image_payload['width']  = $image_payload['width'] ?? (int) $image_size[0];
					$image_payload['height'] = $image_payload['height'] ?? (int) $image_size[1];
				}
			}

			// Still missing dimensions -> fall back to image_url to avoid validation errors
			if ( empty( $image_payload['width'] ) || empty( $image_payload['height'] ) ) {
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'error',
						'Base64 payload missing dimensions - falling back to image_url',
						array(
							'image_id'      => $image_id,
							'has_image_url' => ! empty( $image_url ),
						),
						'api'
					);
				}

				if ( ! empty( $image_url ) ) {
					unset( $image_payload['image_base64'], $image_payload['mime_type'] );
					$image_payload['image_url'] = $image_url;
				} else {
					$image_payload['_error']         = 'missing_image_data';
					$image_payload['_error_message'] = 'Image data missing dimensions for base64 payload';
				}
			}
		}

		// Ensure dimensions exist even when sending URL-only payloads
		if ( ( empty( $image_payload['width'] ) || empty( $image_payload['height'] ) ) && ! empty( $image_url ) ) {
			$file_path = get_attached_file( $image_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$image_info = getimagesize( $file_path );
				if ( $image_info ) {
					$image_payload['width']  = $image_payload['width'] ?? (int) $image_info[0];
					$image_payload['height'] = $image_payload['height'] ?? (int) $image_info[1];
				}
			}
		}

		// Explicitly mark image source for backend validation
		if ( ! empty( $image_payload['image_base64'] ) ) {
			$image_payload['image_source'] = 'base64';
		} elseif ( ! empty( $image_payload['image_url'] ) ) {
			$image_payload['image_source'] = 'url';
		}

		// Get license key and site hash
		$license_key = $this->get_license_key_callback ? call_user_func( $this->get_license_key_callback ) : '';
		$site_hash   = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';
		if ( empty( $site_hash ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
			$site_hash = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		}

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

	}

	$body = array(
		'image'   => array(
			'base64'    => $image_payload['base64'] ?? '',
			'width'     => $image_payload['width'] ?? null,
			'height'    => $image_payload['height'] ?? null,
			'mime_type' => $image_payload['mime_type'] ?? ( $image_payload['mime'] ?? 'image/jpeg' ),
		),
		'context' => array(
			'title'             => $title ?: '',
			'pageTitle'         => $context['page_title'] ?? '',
			'altTextSuggestion' => $context['alt_text_suggestion'] ?? '',
			'caption'           => $caption ?? '',
			'filename'          => $filename ?? '',
		),
	);

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'info',
				'Alt Text API Request Payload',
				array(
					'image_id_param'     => $image_id,
					'regenerate_flag'    => $regenerate,
					'has_base64'         => ! empty( $image_payload['base64'] ),
					'base64_size_kb'     => ! empty( $image_payload['base64'] ) ? round( strlen( $image_payload['base64'] ) / 1024, 2 ) : 0,
					'payload_dimensions' => ( $image_payload['width'] ?? 'missing' ) . 'x' . ( $image_payload['height'] ?? 'missing' ),
					'mime_type'          => $image_payload['mime_type'] ?? 'image/jpeg',
					'endpoint'           => $endpoint,
				),
				'api'
			);
		}

		$extra_headers = array(
			'Content-Type' => 'application/json',
		);

		$site_key = $site_hash ?: $this->get_site_identifier();
		if ( ! empty( $site_key ) ) {
			$extra_headers['X-Site-Key'] = $site_key;
		}

		// Regenerate should bypass cache on the backend.
		if ( $regenerate ) {
			$extra_headers['X-Bypass-Cache'] = 'true';
		}

		$api_token = $this->get_alt_api_token();
		if ( ! empty( $api_token ) ) {
			$extra_headers['Authorization'] = 'Bearer ' . $api_token;
		}

		$response = $this->request_handler->request_with_retry( $endpoint, 'POST', $body, 3, false, $extra_headers, false );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'debug',
				'Alt Text API response',
				array(
					'status_code' => $response['status_code'],
					'success'     => $response['success'] ?? false,
					'data_keys'   => isset( $response['data'] ) && is_array( $response['data'] ) ? array_keys( $response['data'] ) : array(),
				),
				'api'
			);
		}

		if ( ! $response['success'] ) {
			$error_data    = $response['data'] ?? array();
			$error_message = $error_data['message'] ?? $error_data['error'] ?? __( 'Failed to generate alt text', 'beepbeep-ai-alt-text-generator' );
			$error_code    = $error_data['code'] ?? 'api_error';

			return new \WP_Error(
				'api_error',
				$error_message,
				array(
					'code'        => $error_code,
					'status_code' => $response['status_code'],
					'details'     => $error_data['errors'] ?? null,
				)
			);
		}

		$data     = $response['data'];
		$alt_text = $data['altText'] ?? '';

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'info',
				'Alt Text generated (new API)',
				array(
					'image_id' => $image_id,
					'warnings' => $data['warnings'] ?? array(),
					'usage'    => $data['usage'] ?? array(),
					'meta'     => $data['meta'] ?? array(),
				),
				'api'
			);
		}

		return array(
			'alt_text' => $alt_text,
			'usage'    => $data['usage'] ?? array(),
			'meta'     => $data['meta'] ?? array(),
			'warnings' => $data['warnings'] ?? array(),
		);
	}

	/**
	 * Review existing alt text
	 */
	public function review_alt_text( $image_id, $alt_text, $context = array() ) {
		return new \WP_Error( 'review_disabled', __( 'Review is temporarily disabled for the new alt text API.', 'beepbeep-ai-alt-text-generator' ) );
	}

	/**
	 * Validate base64 size matches reported dimensions
	 * 
	 * Rejects "gray zone" cases where base64 is suspiciously small for the dimensions.
	 * OpenAI tokenizes based on actual image dimensions in the data, not metadata.
	 * If base64 contains a larger image than reported, it will be tokenized accordingly.
	 * 
	 * For 1000x750 images, requires at least ~24KB base64 (3x minimum) instead of just 6KB.
	 * This prevents 3,000+ token costs even with detail: 'low'.
	 * 
	 * @param string $base64 The base64-encoded image data
	 * @param int    $width  Reported image width
	 * @param int    $height Reported image height
	 * @return bool True if base64 size is appropriate for dimensions, false otherwise
	 */
	private function validate_base64_size( $base64, $width, $height ) {
		if ( empty( $base64 ) || $width <= 0 || $height <= 0 ) {
			return false;
		}

		$base64_size = strlen( $base64 );
		$pixel_count = $width * $height;

		// Calculate minimum expected base64 size
		// For 1000x750 (750,000 pixels), require at least 24KB base64 (3x minimum)
		// This prevents high token costs even with detail: 'low'
		// Formula: 24KB = 24,576 bytes for 750,000 pixels
		// Bytes per pixel: 24,576 / 750,000 = 0.032768
		// Use a more conservative multiplier (1.5x) to catch edge cases
		// Required size = pixel_count * 0.032768 * 1.5 bytes
		$bytes_per_pixel = ( 24576 / 750000 ) * 1.5; // ~0.049152 (more conservative)
		$min_base64_bytes = intval( $pixel_count * $bytes_per_pixel );

		// For very small images, use a minimum threshold of 10KB (increased from 6KB)
		// This helps catch cases where small base64 might be corrupted/incomplete
		$absolute_min = 10 * 1024; // 10KB
		$required_size = max( $absolute_min, $min_base64_bytes );

		$is_valid = $base64_size >= $required_size;

		if ( ! $is_valid && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'warning',
				'Base64 size too small for reported dimensions (gray zone rejection)',
				array(
					'dimensions'        => $width . 'x' . $height,
					'pixel_count'       => $pixel_count,
					'base64_size_bytes' => $base64_size,
					'base64_size_kb'    => round( $base64_size / 1024, 2 ),
					'required_size_kb'  => round( $required_size / 1024, 2 ),
					'shortfall_kb'      => round( ( $required_size - $base64_size ) / 1024, 2 ),
				),
				'api'
			);
		}

		return $is_valid;
	}

	/**
	 * Prepare image payload for API
	 */
	public function prepare_image_payload( $image_id, $image_url, $title, $caption, $filename ) {
		$file_path = get_attached_file( $image_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'missing_image_data', __( 'Image file not found for encoding.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$encoded = $this->encode_image_simple( $file_path );
		if ( is_wp_error( $encoded ) ) {
			return $encoded;
		}

		return array(
			'base64'    => $encoded['base64'],
			'image_base64' => $encoded['base64'],
			'width'     => $encoded['width'],
			'height'    => $encoded['height'],
			'mime_type' => 'image/jpeg',
			'image_url' => $image_url, // optional; only used if present
		);
	}

	/**
	 * Encode image for backend: resize and ensure bytes-per-pixel is within expected band
	 */
	private function encode_image_for_api( $file_path, $image_id ) {
		return $this->encode_image_simple( $file_path );
	}

	/**
	 * Simple, resilient resize + base64 helper:
	 * - Dynamically adjusts dimension/quality to land bytes-per-pixel in a safe band
	 * - Ensures temp dir exists and cleans up
	 * - Falls back to GD in-memory encode if WP editor/save fails
	 */
	private function encode_image_simple( $file_path ) {
		$info   = getimagesize( $file_path );
		$orig_w = $info ? (int) $info[0] : 0;
		$orig_h = $info ? (int) $info[1] : 0;
		if ( $orig_w <= 0 || $orig_h <= 0 ) {
			return new \WP_Error( 'invalid_image_size', __( 'Could not read image dimensions.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$min_bpp   = 0.03;
		$max_bpp   = 0.09;
		$best      = null;
		$mid_bpp   = ( $min_bpp + $max_bpp ) / 2;
		$max_side  = min( 256, max( $orig_w, $orig_h ) );
		$targetdim = max( 96, (int) $max_side );
		$quality   = 72;

		for ( $i = 0; $i < 6; $i++ ) {
			$scale    = min( 1, $targetdim / max( $orig_w, $orig_h ) );
			$target_w = max( 1, (int) floor( $orig_w * $scale ) );
			$target_h = max( 1, (int) floor( $orig_h * $scale ) );

			$result = $this->encode_with_quality( $file_path, $target_w, $target_h, $orig_w, $orig_h, $quality );
			if ( $result ) {
				$bytes       = strlen( $result['base64'] ) * 3 / 4;
				$pixel_count = max( 1, $result['width'] * $result['height'] );
				$bpp         = $bytes / $pixel_count;

				$score = abs( $bpp - $mid_bpp );
				if ( ! $best || $score < $best['score'] ) {
					$best = array_merge( $result, array( 'score' => $score ) );
				}

				if ( $bpp >= $min_bpp && $bpp <= $max_bpp ) {
					unset( $result['score'] );
					return $result;
				}

				// Adjust: if too large, shrink dim and quality; if too small, bump quality slightly.
				if ( $bpp > $max_bpp ) {
					$targetdim = max( 96, (int) floor( $targetdim * 0.72 ) );
					$quality   = max( 40, $quality - 10 );
				} else {
					$quality = min( 90, $quality + 10 );
					$targetdim = min( $max_side, (int) floor( $targetdim * 1.1 ) );
				}
			} else {
				$quality   = max( 40, $quality - 10 );
				$targetdim = max( 96, (int) floor( $targetdim * 0.85 ) );
			}
		}

		if ( $best ) {
			unset( $best['score'] );
			return $best;
		}

		return new \WP_Error( 'invalid_image_size', __( 'Failed to save resized image.', 'beepbeep-ai-alt-text-generator' ) );
	}

	private function encode_with_quality( $file_path, $target_w, $target_h, $orig_w, $orig_h, $quality ) {
		// Try WP image editor first.
		if ( function_exists( 'wp_get_image_editor' ) ) {
			$editor = wp_get_image_editor( $file_path );
			if ( ! is_wp_error( $editor ) ) {
				$editor->resize( $target_w, $target_h, false );
				if ( method_exists( $editor, 'set_quality' ) ) {
					$editor->set_quality( $quality );
				}

				$upload_dir = wp_upload_dir();
				if ( ! empty( $upload_dir['path'] ) && ! is_dir( $upload_dir['path'] ) ) {
					wp_mkdir_p( $upload_dir['path'] );
				}
				$temp_filename = 'bbai-inline-' . $target_w . 'x' . $target_h . '-' . $quality . '-' . time() . '.jpg';
				$temp_path     = ! empty( $upload_dir['path'] )
					? trailingslashit( $upload_dir['path'] ) . $temp_filename
					: trailingslashit( sys_get_temp_dir() ) . $temp_filename;

				$saved = $editor->save( $temp_path, 'image/jpeg' );
				if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && file_exists( $saved['path'] ) ) {
					$contents = file_get_contents( $saved['path'] );
					@unlink( $saved['path'] );
					if ( $contents !== false ) {
						$base64     = base64_encode( $contents );
						$size_after = getimagesizefromstring( $contents );
						return array(
							'base64' => $base64,
							'width'  => $size_after ? (int) $size_after[0] : $target_w,
							'height' => $size_after ? (int) $size_after[1] : $target_h,
							'mime'   => 'image/jpeg',
						);
					}
				}
			}
		}

		// GD fallback: resize and encode in-memory.
		if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagecreatetruecolor' ) ) {
			$raw = file_get_contents( $file_path );
			if ( $raw !== false ) {
				$src = @imagecreatefromstring( $raw );
				if ( $src !== false ) {
					$dst = imagecreatetruecolor( $target_w, $target_h );
					imagecopyresampled( $dst, $src, 0, 0, 0, 0, $target_w, $target_h, $orig_w, $orig_h );
					ob_start();
					imagejpeg( $dst, null, $quality );
					$jpeg = ob_get_clean();
					imagedestroy( $dst );
					imagedestroy( $src );
					if ( $jpeg !== false ) {
						$base64     = base64_encode( $jpeg );
						$size_after = getimagesizefromstring( $jpeg );
						return array(
							'base64' => $base64,
							'width'  => $size_after ? (int) $size_after[0] : $target_w,
							'height' => $size_after ? (int) $size_after[1] : $target_h,
							'mime'   => 'image/jpeg',
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * Determine if a URL is publicly reachable over HTTPS (not localhost or private IPs)
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	private function is_public_https_url( $url ) {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || strtolower( $parsed['scheme'] ) !== 'https' || empty( $parsed['host'] ) ) {
			return false;
		}

		$host = strtolower( $parsed['host'] );
		// Localhost / local domains
		if ( $host === 'localhost' || $host === '127.0.0.1' || substr( $host, -6 ) === '.local' ) {
			return false;
		}

		// Private IP ranges
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( substr( $host, 0, 4 ) === '10.' ) {
				return false;
			}
			if ( substr( $host, 0, 8 ) === '192.168.' ) {
				return false;
			}
			// 172.16.0.0 – 172.31.255.255
			if ( preg_match( '#^172\.(1[6-9]|2[0-9]|3[0-1])\.#', $host ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resize and encode image file to base64 within size limits
	 *
	 * @param string $file_path
	 * @param int    $image_id
	 * @return array|null { base64, width, height, mime } or null on failure
	 */
	private function generate_resized_base64( $file_path, $image_id ) {
		$mime_type = get_post_mime_type( $image_id ) ?: 'image/jpeg';
		$image_info = getimagesize( $file_path );
		$orig_w     = $image_info ? (int) $image_info[0] : 0;
		$orig_h     = $image_info ? (int) $image_info[1] : 0;
		if ( $orig_w <= 0 || $orig_h <= 0 ) {
			return null;
		}

		// Target ratio-based size: keep base64 length per pixel below ~0.035 (approx backend expectation)
		$max_ratio      = 0.035; // base64 chars per pixel (approx)
		$min_ratio      = 0.018; // avoid being flagged as too small
		$quality_steps  = array( 70, 60, 50, 40, 30, 20 );
		$max_dim_start  = min( 256, max( 128, max( $orig_w, $orig_h ) ) );
		$min_dim        = 96;

		$current_dim = $max_dim_start;
		$best        = null;

		while ( $current_dim >= $min_dim ) {
			foreach ( $quality_steps as $q ) {
				$result = $this->resize_and_encode( $file_path, $orig_w, $orig_h, $current_dim, $q );
				if ( ! $result ) {
					continue;
				}
				$len      = strlen( $result['base64'] );
				$pixelcnt = max( 1, $result['width'] * $result['height'] );
				$ratio    = $len / $pixelcnt;

				// Perfect fit within ratio bounds
				if ( $ratio <= $max_ratio && $ratio >= $min_ratio ) {
					$result['mime'] = $mime_type;
					return $result;
				}

				// Track best candidate (closest to max_ratio without being far below min_ratio)
				if ( $best === null ) {
					$best = $result + array( 'mime' => $mime_type );
					continue;
				}

				$best_len      = strlen( $best['base64'] );
				$best_ratio    = $best_len / max( 1, $best['width'] * $best['height'] );
				$best_score    = abs( $best_ratio - $max_ratio );
				$current_score = abs( $ratio - $max_ratio );

				if ( $current_score < $best_score ) {
					$best = $result + array( 'mime' => $mime_type );
				}
			}

			$current_dim = (int) floor( $current_dim * 0.85 );
		}

		return $best ? $best + array( 'mime' => $mime_type ) : null;
	}

	/**
	 * Helper: resize and encode using wp_get_image_editor or GD fallback
	 */
	private function resize_and_encode( $file_path, $orig_w, $orig_h, $target_dim, $quality ) {
		$ratio      = min( $target_dim / $orig_w, $target_dim / $orig_h, 1 );
		$new_width  = (int) max( 1, floor( $orig_w * $ratio ) );
		$new_height = (int) max( 1, floor( $orig_h * $ratio ) );

		// WP editor path
		if ( function_exists( 'wp_get_image_editor' ) ) {
			$editor = wp_get_image_editor( $file_path );
			if ( ! is_wp_error( $editor ) ) {
				$editor->resize( $new_width, $new_height, false );
				if ( method_exists( $editor, 'set_quality' ) ) {
					$editor->set_quality( $quality );
				}
				$upload_dir    = wp_upload_dir();
				$temp_filename = 'bbai-inline-' . $target_dim . '-' . $quality . '-' . time() . '.jpg';
				$temp_path     = trailingslashit( $upload_dir['path'] ) . $temp_filename;
				$saved         = $editor->save( $temp_path, 'image/jpeg' );
				if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
					$contents = file_get_contents( $saved['path'] );
					@unlink( $saved['path'] );
					if ( $contents !== false ) {
						$base64 = base64_encode( $contents );
						$size_after = getimagesizefromstring( $contents );
						return array(
							'base64' => $base64,
							'width'  => $size_after ? (int) $size_after[0] : $new_width,
							'height' => $size_after ? (int) $size_after[1] : $new_height,
						);
					}
				}
			}
		}

		// GD fallback
		if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagecreatetruecolor' ) ) {
			$raw = file_get_contents( $file_path );
			if ( $raw !== false ) {
				$src = @imagecreatefromstring( $raw );
				if ( $src !== false ) {
					$dst = imagecreatetruecolor( $new_width, $new_height );
					imagecopyresampled( $dst, $src, 0, 0, 0, 0, $new_width, $new_height, $orig_w, $orig_h );
					ob_start();
					imagejpeg( $dst, null, $quality );
					$jpeg = ob_get_clean();
					imagedestroy( $dst );
					imagedestroy( $src );
					if ( $jpeg !== false ) {
						$base64 = base64_encode( $jpeg );
						$size_after = getimagesizefromstring( $jpeg );
						return array(
							'base64' => $base64,
							'width'  => $size_after ? (int) $size_after[0] : $new_width,
							'height' => $size_after ? (int) $size_after[1] : $new_height,
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * Get alt API token from option/constant/env.
	 */
	private function get_alt_api_token() {
		$token = get_option( 'bbai_alt_api_token' );
		if ( empty( $token ) && defined( 'ALT_API_TOKEN' ) ) {
			$token = ALT_API_TOKEN;
		}
		if ( empty( $token ) ) {
			$env_token = getenv( 'ALT_API_TOKEN' );
			if ( $env_token !== false ) {
				$token = $env_token;
			}
		}
		return $token;
	}

	/**
	 * Resolve site identifier for X-Site-Key header.
	 */
	private function get_site_identifier() {
		if ( $this->get_site_id_callback ) {
			$site_id = call_user_func( $this->get_site_id_callback );
			if ( ! empty( $site_id ) ) {
				return $site_id;
			}
		}
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		return \BeepBeepAI\AltTextGenerator\get_site_identifier();
	}

}
