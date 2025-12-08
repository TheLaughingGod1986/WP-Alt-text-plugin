<?php
/**
 * API Client for AltText AI Phase 2
 *
 * Facade class that delegates to specialized service classes for better maintainability.
 * This class maintains backward compatibility while organizing functionality into:
 *
 * - API_Request_Handler: Core HTTP request logic and retry handling
 * - API_Error_Handler: Error parsing and user-friendly error messages
 * - Auth_Manager: Authentication, user management, and identity sync
 * - License_Manager: License key and data management
 * - Usage_Manager: Usage tracking and credit operations
 * - Billing_Client: Billing, plans, and subscription management
 * - Generation_Client: Alt text generation and review
 *
 * All public methods delegate to the appropriate service class while maintaining
 * the original method signatures for backward compatibility.
 *
 * @package BeepBeepAI\AltTextGenerator
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class API_Client_V2 {

	private $api_url;
	private $token_option_key        = 'optti_jwt_token';
	private $user_option_key         = 'beepbeepai_user_data';
	private $site_id_option_key      = 'beepbeepai_site_id';
	private $license_key_option_key  = 'beepbeepai_license_key';
	private $license_data_option_key = 'beepbeepai_license_data';
	private $encryption_prefix       = 'enc:';

	// Service classes
	private $request_handler;
	private $error_handler;
	private $auth_manager;
	private $license_manager;
	private $usage_manager;
	private $billing_client;
	private $generation_client;

	public function __construct() {
		// ALWAYS use production API URL
		$production_url = 'https://alttext-ai-backend.onrender.com';
		$this->api_url  = $production_url;

		// Force update WordPress settings to production (clean up legacy configs)
		$options = get_option( 'beepbeepai_settings', array() );
		if ( $options === false || $options === null ) {
			$options = get_option( 'beepbeepai_settings', array() );
			if ( $options === false || $options === null ) {
				$options = get_option( 'beepbeepai_settings', array() );
			}
		}
		$options = is_array( $options ) ? $options : array();
		if ( ! isset( $options['api_url'] ) || $options['api_url'] !== $production_url ) {
			$options['api_url'] = $production_url;
			update_option( 'beepbeepai_settings', $options );
		}

		// Initialize service classes
		$this->initialize_service_classes();
	}

	/**
	 * Initialize all service classes
	 */
	private function initialize_service_classes() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/api/class-api-request-handler.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/api/class-api-error-handler.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/api/class-auth-manager.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/api/class-license-manager.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/api/class-usage-manager.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/api/class-billing-client.php';
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/api/class-generation-client.php';

		// Build base callbacks (without service class references)
		$base_callbacks = $this->build_base_callbacks();

		// Initialize Request Handler
		$this->request_handler = new \BeepBeepAI\AltTextGenerator\API\API_Request_Handler( $this->api_url, $base_callbacks['request_handler'] );

		// Initialize Error Handler
		$this->error_handler = new \BeepBeepAI\AltTextGenerator\API\API_Error_Handler( $base_callbacks['error_handler'] );

		// Set error handler on request handler
		$this->request_handler->set_error_handler( $this->error_handler );

		// Initialize License Manager first (needed by other managers)
		$this->license_manager = new \BeepBeepAI\AltTextGenerator\API\License_Manager( $this->request_handler, $base_callbacks['license_manager'] );

		// Build extended callbacks that reference License Manager
		$extended_callbacks = $this->build_extended_callbacks();

		// Initialize Auth Manager
		$this->auth_manager = new \BeepBeepAI\AltTextGenerator\API\Auth_Manager( $this->request_handler, $extended_callbacks['auth_manager'] );

		// Initialize Usage Manager (depends on License Manager)
		$this->usage_manager = new \BeepBeepAI\AltTextGenerator\API\Usage_Manager( $this->request_handler, $extended_callbacks['usage_manager'] );

		// Initialize Billing Client
		$this->billing_client = new \BeepBeepAI\AltTextGenerator\API\Billing_Client( $this->request_handler, $base_callbacks['billing_client'] );

		// Initialize Generation Client (depends on License Manager and Usage Manager)
		$this->generation_client = new \BeepBeepAI\AltTextGenerator\API\Generation_Client( $this->request_handler, $this->error_handler, $extended_callbacks['generation_client'] );
	}

	/**
	 * Build base callbacks (without service class references)
	 */
	private function build_base_callbacks() {
		return array(
			'request_handler' => array(
				'get_token'          => array( $this, 'get_token' ),
				'get_license_key'    => array( $this, 'get_license_key' ),
				'get_site_id'        => array( $this, 'get_site_id' ),
				'has_active_license' => array( $this, 'has_active_license' ),
				'log_api_event'      => array( $this, 'log_api_event' ),
			),
			'error_handler'   => array(
				'get_usage'          => array( $this, 'get_usage' ),
				'clear_token'        => array( $this, 'clear_token' ),
				'has_active_license' => array( $this, 'has_active_license' ),
				'get_license_key'    => array( $this, 'get_license_key' ),
				'get_site_id'        => array( $this, 'get_site_id' ),
				'log_api_event'      => array( $this, 'log_api_event' ),
			),
			'license_manager' => array(
				'encrypt_secret'       => array( $this, 'encrypt_secret' ),
				'maybe_decrypt_secret' => array( $this, 'maybe_decrypt_secret' ),
				'get_site_id'          => array( $this, 'get_site_id' ),
				'is_authenticated'     => array( $this, 'is_authenticated' ),
			),
			'billing_client'  => array(
				'get_token'   => array( $this, 'get_token' ),
				'clear_token' => array( $this, 'clear_token' ),
			),
		);
	}

	/**
	 * Build extended callbacks (with service class references)
	 */
	private function build_extended_callbacks() {
		return array(
			'auth_manager'      => array(
				'get_usage'                => array( $this, 'get_usage' ),
				'auto_attach_license'      => array( $this, 'auto_attach_license' ),
				'extract_license_snapshot' => function ( $data ) {
					return $this->license_manager->extract_license_snapshot( $data );
				},
				'apply_license_snapshot'   => function ( $snapshot ) {
					return $this->license_manager->apply_license_snapshot( $snapshot );
				},
				'has_active_license'       => array( $this, 'has_active_license' ),
				'get_site_id'              => array( $this, 'get_site_id' ),
				'get_token'                => array( $this, 'get_token' ),
				'set_token'                => array( $this, 'set_token' ),
				'clear_token'              => array( $this, 'clear_token' ),
				'get_user_data'            => array( $this, 'get_user_data' ),
				'set_user_data'            => array( $this, 'set_user_data' ),
				'is_authenticated'         => array( $this, 'is_authenticated' ),
			),
			'usage_manager'     => array(
				'has_active_license'              => array( $this, 'has_active_license' ),
				'get_license_data'                => array( $this, 'get_license_data' ),
				'get_license_key'                 => array( $this, 'get_license_key' ),
				'set_license_key'                 => array( $this, 'set_license_key' ),
				'extract_license_snapshot'        => function ( $data ) {
					return $this->license_manager->extract_license_snapshot( $data );
				},
				'apply_license_snapshot'          => function ( $snapshot ) {
					return $this->license_manager->apply_license_snapshot( $snapshot );
				},
				'sync_license_usage_snapshot'     => function ( $usage, $organization, $site_data, $snapshot ) {
					return $this->license_manager->sync_license_usage_snapshot( $usage, $organization, $site_data, $snapshot );
				},
				'format_license_usage_from_cache' => function ( $license_data ) {
					return $this->license_manager->format_license_usage_from_cache( $license_data );
				},
				'is_authenticated'                => array( $this, 'is_authenticated' ),
			),
			'generation_client' => array(
				'has_active_license'  => array( $this, 'has_active_license' ),
				'get_token'           => array( $this, 'get_token' ),
				'clear_token'         => array( $this, 'clear_token' ),
				'is_authenticated'    => array( $this, 'is_authenticated' ),
				'auto_attach_license' => array( $this, 'auto_attach_license' ),
				'get_license_key'     => array( $this, 'get_license_key' ),
				'get_license_data'    => array( $this, 'get_license_data' ),
				'set_license_key'     => array( $this, 'set_license_key' ),
				'get_usage'           => array( $this, 'get_usage' ),
				'get_site_id'         => array( $this, 'get_site_id' ),
				'get_user_info'       => array( $this, 'get_user_info' ),
			),
		);
	}


	/**
	 * Get stored JWT token
	 * Checks for optti_jwt_token first, then migrates from legacy keys if found
	 */
	public function get_token() {
		$token = get_option( $this->token_option_key, '' );
		if ( $token === '' || $token === false ) {
			// Check legacy token keys and migrate
			$legacy_keys = array( 'beepbeepai_jwt_token', 'bbai_jwt_token' );
			foreach ( $legacy_keys as $legacy_key ) {
				$legacy = get_option( $legacy_key, '' );
				if ( ! empty( $legacy ) ) {
					// Migrate legacy token to new key
					$this->set_token( $legacy );
					$token = $legacy;
					break;
				}
			}
		}
		$token = is_string( $token ) ? $token : '';
		return $this->maybe_decrypt_secret( $token );
	}

	/**
	 * Store JWT token
	 */
	public function set_token( $token ) {
		if ( empty( $token ) ) {
			$this->clear_token();
			return;
		}

		$stored = $this->encrypt_secret( $token );
		if ( empty( $stored ) ) {
			$stored = $token;
		}
		update_option( $this->token_option_key, $stored, false );
	}

	/**
	 * Clear stored token
	 * Clears both new and legacy token keys for complete cleanup
	 */
	public function clear_token() {
		delete_option( $this->token_option_key );
		// Clear all legacy token keys
		delete_option( 'beepbeepai_jwt_token' );
		delete_option( 'bbai_jwt_token' );
		delete_option( $this->user_option_key );
		delete_option( 'beepbeepai_user_data' );
	}

	/**
	 * Get stored user data
	 */
	public function get_user_data() {
		// Use empty array as default instead of null to prevent WordPress core deprecation warnings
		$data = get_option( $this->user_option_key, array() );
		if ( empty( $data ) || ! is_array( $data ) ) {
			$legacy = get_option( 'beepbeepai_user_data', array() );
			if ( ! empty( $legacy ) && is_array( $legacy ) ) {
				update_option( $this->user_option_key, $legacy );
				$data = $legacy;
			}
		}
		return ( ! empty( $data ) && is_array( $data ) ) ? $data : null;
	}

	/**
	 * Store user data
	 */
	public function set_user_data( $user_data ) {
		update_option( $this->user_option_key, $user_data, false );
	}

	/**
	 * Get stored license key
	 * Also checks framework snapshot and license data if key is not stored directly
	 */
	public function get_license_key() {
		$key = get_option( $this->license_key_option_key, '' );
		if ( $key !== '' && $key !== false ) {
			$decrypted = $this->maybe_decrypt_secret( $key );
			if ( ! empty( $decrypted ) ) {
				return $decrypted;
			}
		}

		// If no key stored, check framework snapshot (for free licenses)
		if ( function_exists( 'opptiai_framework' ) ) {
			$framework = opptiai_framework();
			if ( $framework && isset( $framework->licensing ) ) {
				$snapshot = $framework->licensing->get_snapshot();
				if ( ! empty( $snapshot ) && isset( $snapshot['licenseKey'] ) ) {
					return $snapshot['licenseKey'];
				}
			}
		}

		// Also check license data (might have been stored there)
		$license_data = $this->get_license_data();
		if ( ! empty( $license_data ) ) {
			// Check top-level licenseKey
			if ( isset( $license_data['licenseKey'] ) && ! empty( $license_data['licenseKey'] ) ) {
				return $license_data['licenseKey'];
			}
			// Check site data for licenseKey (stored during auto-attach)
			if ( isset( $license_data['site'] ) && is_array( $license_data['site'] ) && isset( $license_data['site']['licenseKey'] ) && ! empty( $license_data['site']['licenseKey'] ) ) {
				return $license_data['site']['licenseKey'];
			}
		}

		return '';
	}

	/**
	 * Store license key
	 */
	public function set_license_key( $license_key ) {
		if ( empty( $license_key ) ) {
			$this->clear_license_key();
			return;
		}

		$stored = $this->encrypt_secret( $license_key );
		if ( empty( $stored ) ) {
			$stored = $license_key;
		}
		update_option( $this->license_key_option_key, $stored, false );
	}

	/**
	 * Clear stored license key
	 */
	public function clear_license_key() {
		delete_option( $this->license_key_option_key );
		delete_option( $this->license_data_option_key );
	}

	/**
	 * Get stored license data (organization info, etc.)
	 */
	public function get_license_data() {
		// Use empty array as default instead of null to prevent WordPress core deprecation warnings
		$data = get_option( $this->license_data_option_key, array() );
		return ( ! empty( $data ) && is_array( $data ) ) ? $data : null;
	}

	/**
	 * Store license data
	 */
	public function set_license_data( $license_data ) {
		update_option( $this->license_data_option_key, $license_data, false );
	}

	/**
	 * Check if license key is active
	 * For free users, checks framework snapshot if no license key is stored
	 */
	public function has_active_license() {
		$license_key  = $this->get_license_key();
		$license_data = $this->get_license_data();

		// If we have both key and data, license is active
		if ( ! empty( $license_key ) && ! empty( $license_data ) &&
			isset( $license_data['organization'] ) &&
			isset( $license_data['site'] ) ) {
			return true;
		}

		// For free users, check framework snapshot (license may be stored there without a key)
		if ( function_exists( 'opptiai_framework' ) ) {
			$framework = opptiai_framework();
			if ( $framework && isset( $framework->licensing ) ) {
				$snapshot = $framework->licensing->get_snapshot();
				if ( ! empty( $snapshot ) && isset( $snapshot['plan'] ) ) {
					// Free, Pro, or Agency plan detected in snapshot
					return true;
				}
			}
		}

		// Also check if we have license data without a key (free plan scenario)
		// This handles cases where auto-attach succeeded but didn't return a licenseKey
		if ( ! empty( $license_data ) &&
			isset( $license_data['organization'] ) &&
			isset( $license_data['organization']['plan'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user is authenticated (JWT or License Key)
	 * Also validates the token by checking with backend
	 */
	public function is_authenticated() {
		// Check license key first (agency license)
		if ( $this->has_active_license() ) {
			return true;
		}

		// Check JWT token (personal account)
		$token = $this->get_token();

		if ( empty( $token ) ) {
			return false;
		}

		// In local development, just check if token exists
		if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
			return true;
		}

		// Validate token is still valid (check periodically, not every request)
		$last_check      = get_transient( 'bbai_token_last_check' );
		$should_validate = $last_check === false;

		if ( $should_validate ) {
			// Try to fetch user info to validate token
			$user_info = $this->get_user_info();

			if ( is_wp_error( $user_info ) ) {
				$error_code    = $user_info->get_error_code();
				$error_message = strtolower( $user_info->get_error_message() );

				// Only clear token if it's definitely invalid (not a temporary server error)
				// Don't clear on server errors (500), network errors, or timeouts
				$error_message_str = is_string( $error_message ) ? $error_message : '';
				if ( $error_code === 'auth_required' ||
					$error_code === 'user_not_found' ||
					( $error_message_str && strpos( $error_message_str, 'user not found' ) !== false ) ||
					( $error_message_str && strpos( $error_message_str, 'session expired' ) !== false ) ||
					( $error_message_str && strpos( $error_message_str, 'unauthorized' ) !== false ) ) {
					// Token is definitely invalid, clear it
					// WP_DEBUG conditional logging for token invalidation
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log(
							sprintf(
								'[BeepBeep AI] JWT token invalidated and cleared: %s (code: %s)',
								$error_message,
								$error_code
							)
						);
					}
					$this->clear_token();
					return false;
				}
				// For server errors, network issues, etc., don't clear token
				// Just return false for this check, token might still be valid
			} else {
				// Token is valid, cache result for 5 minutes
				set_transient( 'bbai_token_last_check', time(), 5 * MINUTE_IN_SECONDS );
				// WP_DEBUG conditional logging for token validation success
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[BeepBeep AI] JWT token validation succeeded' );
				}
			}
		}

		return true;
	}

	/**
	 * Get or generate unique site ID
	 *
	 * CRITICAL: This ensures quotas are tracked per-site, not per-user.
	 * - Free plan: Site gets exactly 50 credits/month total, shared across all users
	 * - Pro/Agency: Site gets plan credits, shared across all users
	 * - Site ID is sent as X-Site-Hash header to backend for quota tracking
	 * - All users on the same site share the same quota
	 *
	 * @return string Site identifier (stable, generated once per site)
	 */
	public function get_site_id() {
		// Use the centralized helper function
		// This generates a stable site identifier stored in WordPress options (site-wide)
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		return \BeepBeepAI\AltTextGenerator\get_site_identifier();
	}

	/**
	 * Get authentication headers
	 * Includes license key or JWT token, plus site info
	 */
	private function get_auth_headers( $include_user_id = false, $extra_headers = array() ) {
		$token       = $this->get_token();
		$license_key = $this->get_license_key();

		// CRITICAL: If we have an active license but no key, try to retrieve it
		// This handles cases where license data exists but key wasn't stored separately
		if ( empty( $license_key ) && $this->has_active_license() ) {
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

			$license_data = $this->get_license_data();
			if ( ! empty( $license_data ) ) {
				// Check multiple locations for the license key
				if ( isset( $license_data['licenseKey'] ) && ! empty( $license_data['licenseKey'] ) ) {
					$license_key = $license_data['licenseKey'];
					// Store it for future use
					$this->set_license_key( $license_key );
					if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'info',
							'License key retrieved from license_data[licenseKey]',
							array(
								'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
							),
							'auth'
						);
					}
				} elseif ( isset( $license_data['site']['licenseKey'] ) && ! empty( $license_data['site']['licenseKey'] ) ) {
					$license_key = $license_data['site']['licenseKey'];
					// Store it for future use
					$this->set_license_key( $license_key );
					if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'info',
							'License key retrieved from license_data[site][licenseKey]',
							array(
								'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
							),
							'auth'
						);
					}
				} elseif ( function_exists( 'opptiai_framework' ) ) {
					// Check framework snapshot
					$framework = opptiai_framework();
					if ( $framework && isset( $framework->licensing ) ) {
						$snapshot = $framework->licensing->get_snapshot();
						if ( ! empty( $snapshot ) && isset( $snapshot['licenseKey'] ) ) {
							$license_key = $snapshot['licenseKey'];
							// Store it for future use
							$this->set_license_key( $license_key );
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'info',
									'License key retrieved from framework snapshot',
									array(
										'license_key_preview' => substr( $license_key, 0, 20 ) . '...',
									),
									'auth'
								);
							}
						}
					}
				}

				// If still no key after checking all locations, log it
				if ( empty( $license_key ) && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'error',
						'Active license detected but license key not found in any location',
						array(
							'has_license_data'  => ! empty( $license_data ),
							'license_data_keys' => ! empty( $license_data ) ? array_keys( $license_data ) : array(),
							'has_site_data'     => isset( $license_data['site'] ),
							'site_data_keys'    => isset( $license_data['site'] ) && is_array( $license_data['site'] ) ? array_keys( $license_data['site'] ) : array(),
						),
						'auth'
					);
				}
			} elseif ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'error',
						'Active license detected but no license data available',
						array(
							'has_active_license' => true,
							'has_license_data'   => false,
						),
						'auth'
					);
			}
		}

		$site_id = $this->get_site_id();

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

		// Priority: License key > JWT token
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
						'has_active_license'  => $this->has_active_license(),
					),
					'auth'
				);
			}
		} elseif ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;

			// Debug logging if we have license but no key
			if ( $this->has_active_license() && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
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
			// No license key and no token
			if ( $this->has_active_license() && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'Active license detected but no license key or token available',
					array(
						'has_active_license' => true,
						'has_license_key'    => false,
						'has_token'          => false,
					),
					'auth'
				);
			}
		}

		// Merge any caller-provided headers (used for debugging/traceability)
		if ( ! empty( $extra_headers ) && is_array( $extra_headers ) ) {
			$headers = array_merge( $headers, $extra_headers );
		}

		return $headers;
	}

	/**
	 * Encrypt secret values before persisting to the options table.
	 */
	public function encrypt_secret( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) || ( ! function_exists( 'random_bytes' ) && ! function_exists( 'openssl_random_pseudo_bytes' ) ) ) {
			return $value;
		}

		$key = substr( hash( 'sha256', wp_salt( 'auth' ) ), 0, 32 );
		$iv  = function_exists( 'random_bytes' ) ? @random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
		if ( $iv === false ) {
			return $value;
		}

		$cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return $value;
		}

		return $this->encryption_prefix . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt stored secrets when retrieved from options.
	 */
	public function maybe_decrypt_secret( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return '';
		}

		if ( strpos( $value, $this->encryption_prefix ) !== 0 ) {
			return $value;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return substr( $value, strlen( $this->encryption_prefix ) );
		}

		$payload = base64_decode( substr( $value, strlen( $this->encryption_prefix ) ), true );
		if ( $payload === false || strlen( $payload ) < 17 ) {
			return '';
		}

		$iv     = substr( $payload, 0, 16 );
		$cipher = substr( $payload, 16 );
		$key    = substr( hash( 'sha256', wp_salt( 'auth' ) ), 0, 32 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return $plain !== false ? $plain : '';
	}

	/**
	 * Make authenticated API request
	 */
	private function make_request( $endpoint, $method = 'GET', $data = null, $timeout = null, $include_user_id = false, $extra_headers = array(), $include_auth_headers = true ) {
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
			$is_generate_endpoint = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || ( strpos( $endpoint_str, 'api/generate' ) !== false ) );
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
		$is_generate_endpoint = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || ( strpos( $endpoint_str, 'api/generate' ) !== false ) );

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
				$is_generate_endpoint = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || ( strpos( $endpoint_str, 'api/generate' ) !== false ) );
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
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'warning',
				'401/403 response received - logging full response structure',
				array(
					'endpoint'            => $endpoint,
					'status_code'         => $status_code,
					'response_body'       => $body,
					'parsed_data'         => $data,
					'has_active_license'  => $this->has_active_license(),
					'license_key_preview' => substr( $this->get_license_key(), 0, 20 ) . '...',
					'site_hash'           => $this->get_site_id(),
				),
				'api'
			);
		}

		// Handle 404 - endpoint not found
		if ( $status_code === 404 ) {
			// Check if it's an HTML error page (means endpoint doesn't exist)
			$body_str     = is_string( $body ) ? $body : '';
			$endpoint_str = is_string( $endpoint ) ? $endpoint : '';
			if ( $body_str && ( strpos( $body_str, '<html' ) !== false || strpos( $body_str, 'Cannot POST' ) !== false || strpos( $body_str, 'Cannot GET' ) !== false ) ) {
				// Provide context-specific error messages
				$error_message = __( 'This feature is not yet available. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator' );

				// Check endpoint to provide more specific message
				if ( $endpoint_str && ( strpos( $endpoint_str, '/auth/forgot-password' ) !== false || strpos( $endpoint_str, '/auth/reset-password' ) !== false ) ) {
					$error_message = __( 'Password reset functionality is currently being set up on our backend. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator' );
				} elseif ( $endpoint_str && ( strpos( $endpoint_str, '/licenses/sites' ) !== false || strpos( $endpoint_str, '/api/licenses/sites' ) !== false ) ) {
					$error_message = __( 'License site usage tracking is currently being set up on our backend. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator' );
				}

				return new \WP_Error(
					'endpoint_not_found',
					$error_message
				);
			}
			return new \WP_Error(
				'not_found',
				$data['error'] ?? $data['message'] ?? __( 'The requested resource was not found.', 'beepbeep-ai-alt-text-generator' )
			);
		}

		// Handle NO_ACCESS errors (can be in any status code response, including 403)
		// Check for both uppercase and lowercase variations, and also check nested data structure
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

			// Also check nested data.code (backend sometimes nests it)
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

		if ( $no_access_code && $no_access_data ) {
			// Extract error fields from new backend structure (use $no_access_data which may be nested)
			$credits              = isset( $no_access_data['credits'] ) ? intval( $no_access_data['credits'] ) : null;
			$subscription_expired = isset( $no_access_data['subscription_expired'] ) ? (bool) $no_access_data['subscription_expired'] : false;
			$reason               = isset( $no_access_data['reason'] ) && is_string( $no_access_data['reason'] ) ? $no_access_data['reason'] : '';
			$message              = isset( $no_access_data['message'] ) && is_string( $no_access_data['message'] ) ? $no_access_data['message'] : '';

			// Fallback to top-level data if not found in nested structure
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

			// Determine error type based on context
			// Check reason field first (backend sends reason: "no_credits" for quota issues)
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

			// If it's a quota error (no_credits), check if cached usage disagrees with backend
			// This handles cases where backend reports no credits but usage endpoint shows credits available
			if ( $is_quota_error ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

				// If cached usage shows credits available, do a fresh check
				if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] > 0 ) {
					$fresh_usage = $this->get_usage();

					if ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) && isset( $fresh_usage['remaining'] ) && is_numeric( $fresh_usage['remaining'] ) && $fresh_usage['remaining'] > 0 ) {
						// Fresh API check shows credits available - backend NO_ACCESS was incorrect
						// Update cache with fresh data and return a retry error instead of blocking
						\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );

						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							\BeepBeepAI\AltTextGenerator\Debug_Log::log(
								'warning',
								'Backend returned NO_ACCESS with no_credits but cache and fresh API check show credits available',
								array(
									'cached_remaining' => $cached_usage['remaining'],
									'api_remaining'    => $fresh_usage['remaining'],
									'backend_reason'   => $reason,
									'backend_message'  => $message,
								),
								'api'
							);
						}

						// Return a retry error instead of blocking completely
						return new \WP_Error(
							'quota_check_mismatch',
							sprintf(
								__( 'Temporary backend sync issue detected. You have %d credits available. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' ),
								$fresh_usage['remaining']
							),
							array(
								'usage'       => $fresh_usage,
								'retry_after' => 3,
								'error_code'  => 'quota_check_mismatch',
								'retryable'   => true,
							)
						);
					} elseif ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) && isset( $fresh_usage['remaining'] ) && is_numeric( $fresh_usage['remaining'] ) && $fresh_usage['remaining'] === 0 ) {
						// Fresh API check confirms 0 credits - backend was correct, update cache
						\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );
					}
				}
			}

			// Build error message with fallback hierarchy:
			// 1. Use backend message if provided
			// 2. Use reason if provided
			// 3. Use context-based default messages
			// 4. Use generic fallback
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

		// Handle subscription errors (402 Payment Required)
		if ( $status_code === 402 ) {
			$error_code    = '';
			$error_message = '';

			if ( is_array( $data ) ) {
				$error_code    = is_string( $data['error'] ?? $data['code'] ?? '' ) ? ( $data['error'] ?? $data['code'] ?? '' ) : 'subscription_required';
				$error_message = is_string( $data['message'] ?? '' ) ? $data['message'] : __( 'A subscription is required to continue.', 'beepbeep-ai-alt-text-generator' );
			} else {
				$error_code    = 'subscription_required';
				$error_message = __( 'A subscription is required to continue.', 'beepbeep-ai-alt-text-generator' );
			}

			// Normalize error codes to expected values
			$normalized_code = 'subscription_required';
			// CRITICAL: Ensure error_code is a string before using strpos (PHP 8.1+ compatibility)
			$error_code_str = is_string( $error_code ) ? $error_code : '';
			if ( $error_code === 'subscription_expired' || ( ! empty( $error_code_str ) && strpos( strtolower( $error_code_str ), 'expired' ) !== false ) ) {
				$normalized_code = 'subscription_expired';
			} elseif ( $error_code === 'quota_exceeded' || ( ! empty( $error_code_str ) && strpos( strtolower( $error_code_str ), 'quota' ) !== false ) || $error_code === 'out_of_credits' ) {
				$normalized_code = 'out_of_credits';
			}

			// If it's an out_of_credits error, check if cached usage disagrees
			if ( $normalized_code === 'out_of_credits' ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

				// If cached usage shows credits available, do a fresh check
				if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] > 0 ) {
					$fresh_usage = $this->get_usage();

					if ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) && isset( $fresh_usage['remaining'] ) && is_numeric( $fresh_usage['remaining'] ) && $fresh_usage['remaining'] > 0 ) {
						// Fresh API check shows credits available - backend 402 was incorrect
						// Update cache with fresh data and return a retry error instead of blocking
						\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );

						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							\BeepBeepAI\AltTextGenerator\Debug_Log::log(
								'warning',
								'Backend returned 402 out_of_credits but cache and fresh API check show credits available',
								array(
									'cached_remaining' => $cached_usage['remaining'],
									'api_remaining'    => $fresh_usage['remaining'],
									'backend_error'    => $error_message,
								),
								'api'
							);
						}

						// Return a retry error instead of blocking completely
						return new \WP_Error(
							'quota_check_mismatch',
							sprintf(
								__( 'Temporary backend sync issue detected. You have %d credits available. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' ),
								$fresh_usage['remaining']
							),
							array(
								'usage'       => $fresh_usage,
								'retry_after' => 3,
								'error_code'  => 'quota_check_mismatch',
								'retryable'   => true,
							)
						);
					} elseif ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) && isset( $fresh_usage['remaining'] ) && is_numeric( $fresh_usage['remaining'] ) && $fresh_usage['remaining'] === 0 ) {
						// Fresh API check confirms 0 credits - backend was correct, update cache
						\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );
					}
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

			// Cache subscription error for banner display (expires in 1 hour)
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

		// Handle server errors
		if ( $status_code >= 500 ) {
			// Log the actual response body for debugging
			$error_details = '';
			if ( is_array( $data ) && isset( $data['error'] ) ) {
				$error_details = is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
			} elseif ( is_array( $data ) && isset( $data['message'] ) ) {
				$error_details = is_string( $data['message'] ) ? $data['message'] : '';
			} elseif ( ! empty( $body ) && strlen( $body ) < 500 ) {
				// Only include body if it's not too long
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

			// Try to extract more specific error from response body
			$backend_error_code    = '';
			$backend_error_message = '';

			if ( is_array( $data ) ) {
				$backend_error_code    = is_string( $data['code'] ?? '' ) ? $data['code'] : '';
				$backend_error_message = is_string( $data['message'] ?? $data['error'] ?? '' ) ? ( $data['message'] ?? $data['error'] ?? '' ) : '';
			}

			// Check for "User not found" errors first (these come back as 500 sometimes)
			// Exception: For checkout endpoints, don't clear token here - let create_checkout_session() handle retry without token
			$endpoint_str         = is_string( $endpoint ) ? $endpoint : '';
			$is_checkout_endpoint = strpos( $endpoint_str, '/billing/checkout' ) !== false;

			$error_details_str         = is_string( $error_details ) ? $error_details : '';
			$backend_error_message_str = is_string( $backend_error_message ) ? $backend_error_message : '';
			$error_details_lower       = strtolower( $error_details_str . ' ' . $backend_error_message_str );
			if ( strpos( $error_details_lower, 'user not found' ) !== false ||
				strpos( $error_details_lower, 'user does not exist' ) !== false ||
				( is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) && strpos( strtolower( $data['code'] ), 'user_not_found' ) !== false ) ) {

				// For checkout, return error without clearing token so it can retry without auth
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

				// For other endpoints, clear token and return auth_required error
				$this->clear_token();
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

			// Provide more specific error message based on endpoint and error details
			$error_message = __( 'The server encountered an error processing your request. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );

			// Check for generate endpoint (with or without leading slash)
			$endpoint_str            = is_string( $endpoint ) ? $endpoint : '';
			$is_generate_endpoint    = $endpoint_str && ( ( strpos( $endpoint_str, '/api/generate' ) !== false ) || ( strpos( $endpoint_str, 'api/generate' ) !== false ) );
			$is_checkout_endpoint    = strpos( $endpoint_str, '/billing/checkout' ) !== false;
			$is_auto_attach_endpoint = strpos( $endpoint_str, '/api/licenses/auto-attach' ) !== false;

			// Check for database schema errors (backend issue)
			$error_details_str         = is_string( $error_details ) ? $error_details : '';
			$backend_error_message_str = is_string( $backend_error_message ) ? $backend_error_message : '';
			$error_details_lower       = strtolower( $error_details_str . ' ' . $backend_error_message_str );
			$is_schema_error           = strpos( $error_details_lower, 'column' ) !== false &&
								( strpos( $error_details_lower, 'schema cache' ) !== false ||
								strpos( $error_details_lower, 'not found' ) !== false ||
								strpos( $error_details_lower, 'does not exist' ) !== false );

			if ( $is_auto_attach_endpoint ) {
				// Auto-attach is optional - don't show critical errors to users
				if ( $is_schema_error ) {
					// Backend database schema issue - log but don't fail critically
					$error_message = __( 'License auto-attachment is temporarily unavailable due to a backend maintenance issue. Your plugin will continue to work normally. Please try again later or contact support if needed.', 'beepbeep-ai-alt-text-generator' );
				} else {
					$error_message = __( 'License auto-attachment failed. Your plugin will continue to work normally. You can manually activate your license using the key from your email if needed.', 'beepbeep-ai-alt-text-generator' );
				}
			} elseif ( $is_checkout_endpoint ) {
				// For checkout, provide a more user-friendly error message
				// The backend is having issues with checkout - likely needs authentication fix
				$error_message = __( 'Unable to create checkout session. This may be a temporary backend issue. Please try again in a moment or contact support if the problem persists.', 'beepbeep-ai-alt-text-generator' );
			} elseif ( $is_generate_endpoint ) {
				// Check for schema errors first (backend database issue)
				if ( $is_schema_error ) {
					$error_message = __( 'The image generation service is temporarily unavailable due to backend maintenance. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
				} else {
					// Check if it's an OpenAI API key issue (backend configuration problem)
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
				// Check if auth endpoint error is due to schema issue
				if ( $is_schema_error ) {
					$error_message = __( 'Authentication is temporarily unavailable due to backend maintenance. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
				} else {
					$error_message = __( 'The authentication server is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
				}
			} elseif ( $is_schema_error ) {
				// Generic database schema error handling
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

		// Clear subscription error transient on successful API calls (subscription is valid)
		if ( $status_code >= 200 && $status_code < 300 ) {
			delete_transient( 'bbai_subscription_error' );
		}

		// Handle authentication errors - only clear token if it's a clear auth issue
		// Don't clear on temporary backend errors or if endpoint doesn't require auth
		// IMPORTANT: Check for NO_ACCESS/subscription errors first (handled above),
		// only treat as auth error if it's not a subscription issue
		if ( $status_code === 401 || $status_code === 403 ) {
			// Check if this is a billing/checkout endpoint (can work without auth)
			$endpoint_str         = is_string( $endpoint ) ? $endpoint : '';
			$is_checkout_endpoint = strpos( $endpoint_str, '/billing/checkout' ) !== false;

			// Double-check: if response contains subscription-related errors, don't treat as auth error
			// (This is a safety check - NO_ACCESS should have been caught above)
			$is_subscription_error = false;
			if ( is_array( $data ) ) {
				$code_str = isset( $data['code'] ) && is_string( $data['code'] ) ? strtoupper( $data['code'] ) : '';
				if ( $code_str === 'NO_ACCESS' || $code_str === 'NOACCESS' ||
					( isset( $data['no_access'] ) && $data['no_access'] === true ) ||
					( isset( $data['data']['no_access'] ) && $data['data']['no_access'] === true ) ) {
					$is_subscription_error = true;
				}
			}

			// For checkout or subscription errors, don't clear token - let the caller handle it
			// Also don't clear token if user has an active license (license-based auth works without token)
			if ( ! $is_checkout_endpoint && ! $is_subscription_error && ! $this->has_active_license() ) {
				// Only clear token for non-checkout, non-subscription endpoints, and only if no license
				$this->clear_token();
				delete_transient( 'bbai_token_last_check' );
			}

			// If it's a subscription error, it should have been handled above, but if we get here,
			// return a more appropriate error
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

			// If user has an active license, don't treat 401/403 as authentication error
			// It might be a license validation issue or backend error
			if ( $this->has_active_license() ) {
				// Log the actual response structure for debugging
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
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
							'license_key_preview' => substr( $this->get_license_key(), 0, 20 ) . '...',
							'site_hash'           => $this->get_site_id(),
						),
						'api'
					);
				}

				// Check if usage endpoint shows we have access (to distinguish license validation from quota)
				// This helps identify if it's a license key recognition issue vs quota issue
				$usage_check      = $this->get_usage();
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

				// If usage endpoint works, it's likely a license key recognition issue on the generate endpoint
				// If usage endpoint also fails, it's a broader license validation issue
				if ( $has_usage_access ) {
					// Usage endpoint works but generate endpoint doesn't - check backend response for specific error
					// Backend logs show license key IS being received, so it's a validation issue, not a transmission issue
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
						// Backend received the key (logs confirm hasLicenseKey: true) but validation failed
						$error_message = __( 'License key received but validation failed on generation endpoint. Please check your license key configuration or contact support.', 'beepbeep-ai-alt-text-generator' );
					}

					if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'error',
							'License key received by backend but generate endpoint validation failed',
							array(
								'license_key_preview' => substr( $this->get_license_key(), 0, 20 ) . '...',
								'site_hash'           => $this->get_site_id(),
								'usage_remaining'     => $usage_check['remaining'] ?? 'unknown',
								'backend_message'     => $backend_message,
								'response_data'       => $data,
							),
							'licensing'
						);
					}
				} else {
					// Both endpoints failing - broader license validation issue
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

			return new \WP_Error(
				'auth_required',
				__( 'Authentication required. Please log in to continue.', 'beepbeep-ai-alt-text-generator' ),
				array( 'requires_auth' => true )
			);
		}

		return array(
			'status_code' => $status_code,
			'data'        => $data,
			'success'     => $status_code >= 200 && $status_code < 300,
		);
	}

	/**
	 * Wrapper that retries transient failures (502, 503, timeouts) with backoff.
	 */
	private function request_with_retry( $endpoint, $method = 'GET', $data = null, $max_attempts = 3, $include_user_id = false, $extra_headers = array(), $include_auth_headers = true ) {
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
	 * Determine whether a request should be retried.
	 */
	private function should_retry_api_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$code = $error->get_error_code();

		// Don't retry on OpenAI errors - backend handles these
		if ( in_array( $code, array( 'openai_rate_limit', 'OPENAI_RATE_LIMIT', 'GENERATION_ERROR' ), true ) ) {
			return false;
		}

		$retryable_codes = array( 'server_error', 'api_timeout', 'api_unreachable', 'quota_check_mismatch' );
		
		// Handle rate_limit errors separately - use exponential backoff
		if ( $code === 'rate_limit' ) {
			$data = $error->get_error_data();
			$retry_after = isset( $data['retry_after'] ) ? intval( $data['retry_after'] ) : 10;
			// Rate limits should be retried but with longer delays
			return true;
		}
		
		if ( ! in_array( $code, $retryable_codes, true ) ) {
			return false;
		}

		$data   = $error->get_error_data();
		$status = isset( $data['status_code'] ) ? intval( $data['status_code'] ) : 0;

		// Retry network errors without status codes, and HTTP 5xx responses.
		if ( $status === 0 ) {
			return true;
		}

		// Retry on: 408 (Timeout), 429 (Rate Limit), 500, 502, 503, 504
		// Note: 429 is retried only if it's not an OpenAI rate limit (handled above)
		return in_array( $status, array( 408, 429, 500, 502, 503, 504 ), true );
	}

	/**
	 * Register new user
	 * Includes site_id to prevent multiple free accounts per site
	 */
	public function register( $email, $password ) {
		return $this->auth_manager->register( $email, $password );
	}

	/**
	 * @deprecated Internal method - use Auth_Manager directly
	 */
	private function register_original( $email, $password ) {
		// Check if site already has an account
		$existing_token = $this->get_token();
		if ( ! empty( $existing_token ) ) {
			// Site already has an account - check if it's a free plan
			$usage = $this->get_usage();
			if ( ! is_wp_error( $usage ) && isset( $usage['plan'] ) && $usage['plan'] === 'free' ) {
				return new \WP_Error(
					'free_plan_exists',
					__( 'A free plan has already been used for this site. Upgrade to Pro or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator' ),
					array( 'code' => 'free_plan_already_used' )
				);
			}
		}

		// Get site identifier
		$site_id = $this->get_site_id();
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();

		$response = $this->make_request(
			'/auth/register',
			'POST',
			array(
				'email'             => $email,
				'password'          => $password,
				'site_id'           => $site_id,
				'site_url'          => get_site_url(),
				'plugin_version'    => defined( 'BBAI_VERSION' ) ? BBAI_VERSION : '1.0.0',
				'wordpress_version' => get_bloginfo( 'version' ),
			),
			null,
			false,
			array(),
			false
		);

		if ( is_wp_error( $response ) ) {
			// Check for "free plan already used" error from backend
			$error_message = strtolower( $response->get_error_message() );
			// CRITICAL: Ensure error_message is a string before using strpos (PHP 8.1+ compatibility)
			$error_message_str = is_string( $error_message ) ? $error_message : '';
			if ( ( ! empty( $error_message_str ) && ( strpos( $error_message_str, 'free plan' ) !== false ||
				strpos( $error_message_str, 'already used' ) !== false ) ) ||
				$response->get_error_code() === 'free_plan_exists' ) {
				return new \WP_Error(
					'free_plan_exists',
					__( 'A free plan has already been used for this site. Upgrade to Pro or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator' ),
					array( 'code' => 'free_plan_already_used' )
				);
			}
			return $response;
		}

		if ( $response['success'] && isset( $response['data']['token'] ) ) {
			$this->set_token( $response['data']['token'] );
			if ( isset( $response['data']['user'] ) ) {
				$this->set_user_data( $response['data']['user'] );
			}

			$snapshot = $this->extract_license_snapshot( $response['data'] );
			if ( $snapshot ) {
				$this->apply_license_snapshot( $snapshot );
			} else {
				// If no license snapshot in response, try to auto-attach for free users
				// This ensures free users get a site-wide license key automatically
				if ( ! $this->has_active_license() ) {
					$auto_attach_result = $this->auto_attach_license();
					// Log but don't fail registration if auto-attach fails
					if ( is_wp_error( $auto_attach_result ) && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'warning',
							'Auto-attach license failed during registration',
							array(
								'error' => $auto_attach_result->get_error_message(),
							),
							'licensing'
						);
					}
				}
			}

			return $response['data'];
		}

		return new \WP_Error(
			'registration_failed',
			$response['data']['error'] ?? __( 'Registration failed', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Login user
	 * Includes site_id for account linking
	 */
	public function login( $email, $password ) {
		return $this->auth_manager->login( $email, $password );
	}

	/**
	 * Get current user info
	 */
	public function get_user_info() {
		return $this->auth_manager->get_user_info();
	}

	/**
	 * Get user email for identity sync
	 *
	 * @return string User email, or empty string if not available
	 */
	private function get_user_email() {
		// First try: Get email from stored user data
		$user_data = $this->get_user_data();
		if ( ! empty( $user_data ) && is_array( $user_data ) && ! empty( $user_data['email'] ) ) {
			return sanitize_email( $user_data['email'] );
		}

		// Fallback: Get email from current WordPress user
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->exists() && ! empty( $current_user->user_email ) ) {
			return sanitize_email( $current_user->user_email );
		}

		return '';
	}

	/**
	 * Sync identity data to backend
	 *
	 * Sends identity sync call to backend when:
	 * - User logs in via plugin
	 * - Plugin installation occurs
	 * - Plugin refreshes token
	 * - Daily sync if plugin is actively used
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure (errors are logged but not blocking)
	 */
	public function sync_identity() {
		return $this->auth_manager->sync_identity();
	}

	/**
	 * @deprecated Internal method - use Auth_Manager directly
	 */
	private function sync_identity_original() {
		// Get user email
		$email = $this->get_user_email();

		// If no email available and not authenticated, skip sync
		if ( empty( $email ) && ! $this->is_authenticated() ) {
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log( 'info', 'Skipping identity sync - no email and not authenticated', array(), 'identity' );
			}
			return false;
		}

		// Get site URL (equivalent to window.location.origin in JS)
		$site_url   = home_url();
		$parsed_url = wp_parse_url( $site_url );
		if ( $parsed_url ) {
			$site = $parsed_url['scheme'] . '://' . $parsed_url['host'];
			if ( ! empty( $parsed_url['port'] ) ) {
				$site .= ':' . $parsed_url['port'];
			}
		} else {
			$site = $site_url;
		}

		// Get installation ID (optional)
		$site_id         = $this->get_site_id();
		$installation_id = ! empty( $site_id ) ? $site_id : null;

		// Get JWT token (optional)
		$jwt_token = $this->get_token();

		// Build payload
		$payload = array(
			'email'  => $email,
			'plugin' => 'beepbeep-ai',
			'site'   => $site,
		);

		// Add optional fields only if they exist
		if ( ! empty( $installation_id ) ) {
			$payload['installationId'] = $installation_id;
		}

		if ( ! empty( $jwt_token ) ) {
			$payload['jwt'] = $jwt_token;
		}

		// Debug logging to see what's being sent
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'debug',
				'Identity sync payload',
				array(
					'email'               => $email,
					'site'                => $site,
					'has_installation_id' => ! empty( $installation_id ),
					'has_jwt'             => ! empty( $jwt_token ),
					'payload_keys'        => array_keys( $payload ),
				),
				'identity'
			);
		}

		// Make request to identity sync endpoint
		// Use include_auth_headers = false since we're sending jwt in the payload
		$response = $this->make_request( '/identity/sync', 'POST', $payload, 30, false, array(), false );

		if ( is_wp_error( $response ) ) {
			// Log error but don't block operations
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Identity sync failed',
					array(
						'error' => $response->get_error_message(),
						'code'  => $response->get_error_code(),
					),
					'identity'
				);
			}
			// WP_DEBUG conditional logging for detailed debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[BeepBeep AI] Identity sync failed: %s (code: %s)',
						$response->get_error_message(),
						$response->get_error_code()
					)
				);
			}
			return $response;
		}

		// Check if response indicates failure (400+ status codes return success: false)
		if ( isset( $response['success'] ) && ! $response['success'] ) {
			$status_code   = $response['status_code'] ?? 0;
			$error_message = '';
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				$error_message = $response['data']['error'] ?? $response['data']['message'] ?? '';
			}

			// Log error but don't block operations
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Identity sync failed',
					array(
						'status_code' => $status_code,
						'error'       => $error_message ?: 'Bad Request',
						'payload'     => $payload,
					),
					'identity'
				);
			}
			// WP_DEBUG conditional logging for detailed debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[BeepBeep AI] Identity sync failed: status=%d, error=%s',
						$status_code,
						$error_message ?: 'Bad Request'
					)
				);
			}
			return new \WP_Error(
				'identity_sync_failed',
				$error_message ?: sprintf( __( 'Identity sync failed with status %d', 'beepbeep-ai-alt-text-generator' ), $status_code ),
				array( 'status_code' => $status_code )
			);
		}

		// Log success
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'info',
				'Identity sync succeeded',
				array(
					'email' => $email,
					'site'  => $site,
				),
				'identity'
			);
		}
		// WP_DEBUG conditional logging for detailed debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[BeepBeep AI] Identity sync succeeded: email=%s, site=%s',
					$email,
					$site
				)
			);
		}

		return true;
	}

	/**
	 * Activate license key for this site
	 *
	 * Note: The backend handles sending welcome/activation emails automatically
	 * when a license is activated. The plugin does not send emails directly.
	 */
	public function activate_license( $license_key ) {
		return $this->license_manager->activate_license( $license_key );
	}

	/**
	 * @deprecated Internal method - use License_Manager directly
	 */
	private function activate_license_original( $license_key ) {
		$site_id = $this->get_site_id();

		$data = array(
			'licenseKey'       => $license_key,
			'siteHash'         => $site_id,
			'siteUrl'          => get_site_url(),
			'installId'        => $site_id,
			'pluginVersion'    => defined( 'BBAI_VERSION' ) ? BBAI_VERSION : '1.0.0',
			'wordpressVersion' => get_bloginfo( 'version' ),
			'phpVersion'       => PHP_VERSION,
			'isMultisite'      => is_multisite(),
		);

		$response = $this->make_request( '/api/license/activate', 'POST', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			// Store license key and data
			$this->set_license_key( $license_key );
			$this->set_license_data(
				array(
					'organization' => $response['organization'],
					'site'         => $response['site'],
					'activated_at' => current_time( 'mysql' ),
				)
			);

			return array(
				'success'      => true,
				'organization' => $response['organization'],
				'site'         => $response['site'],
			);
		}

		return new \WP_Error(
			'license_activation_failed',
			$response['error'] ?? __( 'Failed to activate license', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Deactivate current license key
	 */
	public function deactivate_license() {
		return $this->license_manager->deactivate_license();
	}

	/**
	 * Auto-attach license to this site after checkout/sign-up.
	 *
	 * @param array $args Optional overrides (siteUrl, siteHash, installId).
	 * @return array|\WP_Error
	 */
	public function auto_attach_license( $args = array() ) {
		return $this->license_manager->auto_attach_license( $args );
	}

	/**
	 * @deprecated Internal method - use License_Manager directly
	 */
	private function auto_attach_license_original( $args = array() ) {
		$site_hash = $this->get_site_id();

		$defaults = array(
			'siteUrl'   => get_site_url(),
			'siteHash'  => $site_hash,
			'installId' => $site_hash,
		);

		$payload = array_merge(
			$defaults,
			array_filter(
				(array) $args,
				static function ( $value ) {
					return $value !== null && $value !== '';
				}
			)
		);

		$response = $this->make_request( '/api/licenses/auto-attach', 'POST', $payload, null, true );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Handle nested response structure: backend returns { success: true, data: { license: ... } }
		// but make_request wraps it, so we need to check response['data']['data'] if it exists
		$response_data = $response['data'] ?? array();
		if ( isset( $response_data['data'] ) && is_array( $response_data['data'] ) ) {
			// Backend response is nested: { success: true, data: { license: ... } }
			$response_data = $response_data['data'];
		}

		$snapshot = $this->extract_license_snapshot( $response_data );

		if ( $response['success'] && $snapshot ) {
			$this->apply_license_snapshot( $snapshot );
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'info',
					'License auto-attach succeeded',
					array(
						'plan'       => $snapshot['plan'] ?? '',
						'tokenLimit' => $snapshot['tokenLimit'] ?? '',
						'siteHash'   => $payload['siteHash'] ?? '',
					),
					'licensing'
				);
			}
			return array(
				'success' => true,
				'message' => $response_data['message'] ?? __( 'License attached successfully', 'beepbeep-ai-alt-text-generator' ),
				'license' => $snapshot,
				'site'    => isset( $response_data['site'] ) ? $response_data['site'] : array(),
			);
		}

		// Check if this is a schema error (backend database issue)
		$error_message       = $response_data['message'] ?? __( 'Failed to auto-attach license', 'beepbeep-ai-alt-text-generator' );
		$error_details       = $response_data['error_details'] ?? $response_data['error'] ?? '';
		$error_details_lower = is_string( $error_details ) ? strtolower( $error_details ) : '';
		$is_schema_error     = strpos( $error_details_lower, 'column' ) !== false &&
							( strpos( $error_details_lower, 'schema cache' ) !== false ||
							strpos( $error_details_lower, 'not found' ) !== false ||
							strpos( $error_details_lower, 'does not exist' ) !== false );

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			$log_level = $is_schema_error ? 'warning' : 'error';
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				$log_level,
				'License auto-attach failed',
				array(
					'siteHash'         => $payload['siteHash'] ?? '',
					'response'         => $response_data,
					'response_success' => $response['success'] ?? false,
					'has_snapshot'     => ! empty( $snapshot ),
					'is_schema_error'  => $is_schema_error,
				),
				'licensing'
			);
		}

		// For schema errors, return a more user-friendly message indicating it's a backend issue
		if ( $is_schema_error ) {
			$error_message = __( 'License auto-attachment is temporarily unavailable due to backend maintenance. Your plugin will continue to work normally.', 'beepbeep-ai-alt-text-generator' );
		}

		return new \WP_Error(
			'auto_attach_failed',
			$error_message,
			array(
				'is_schema_error' => $is_schema_error,
				'siteHash'        => $payload['siteHash'] ?? '',
			)
		);
	}

	/**
	 * Get license site usage statistics
	 * Returns sites using the license and their generation counts
	 */
	public function get_license_sites() {
		return $this->license_manager->get_license_sites();
	}

	/**
	 * Disconnect a site from the license
	 * Removes the installation from the backend
	 */
	public function disconnect_license_site( $site_id ) {
		return $this->license_manager->disconnect_license_site( $site_id );
	}

	/**
	 * Get usage information
	 */
	public function get_usage() {
		return $this->usage_manager->get_usage();
	}

	/**
	 * Check backend API health
	 *
	 * @return array|\WP_Error Health status information
	 */
	public function check_health() {
		$response = $this->make_request( '/health', 'GET', null, null, false, array(), false );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] && isset( $response['data'] ) ) {
			return $response['data'];
		}

		return new \WP_Error(
			'health_check_failed',
			__( 'Backend health check failed', 'beepbeep-ai-alt-text-generator' ),
			array( 'response' => $response )
		);
	}

	/**
	 * @deprecated Internal method - use Usage_Manager directly
	 */
	private function get_usage_original() {
		$has_license   = $this->has_active_license();
		$license_cache = $has_license ? $this->get_license_data() : null;

		// CRITICAL: Do NOT include user ID - usage must be tracked per-site, not per-user
		// For free plans, all users on a site share the same 50 credits
		// The backend should use X-Site-Hash header (sent automatically) to track usage per-site
		$response = $this->make_request( '/usage', 'GET', null, null, false );  // include_user_id = false

		if ( is_wp_error( $response ) ) {
			// If API call fails, try to use cached usage as fallback
			if ( $has_license && $license_cache ) {
				$cached_usage = $this->format_license_usage_from_cache( $license_cache );
				if ( $cached_usage ) {
					return $cached_usage;
				}
			}

			// For non-license accounts, try cached usage as fallback
			if ( ! $has_license ) {
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );
				// Only use cached usage if it's a valid array with remaining credits >= 0
				// This ensures we have valid data before falling back to cache
				if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] >= 0 ) {
					// Return cached usage if available
					return $cached_usage;
				}
			}

			return $response;
		}

		if ( $response['success'] && isset( $response['data']['usage'] ) ) {
			$usage = $response['data']['usage'];

			if ( $has_license && is_array( $usage ) ) {
				// Extract and apply license snapshot if present (this will store the license key)
				$snapshot = $this->extract_license_snapshot( $response['data'] );
				if ( $snapshot ) {
					$this->apply_license_snapshot( $snapshot );
				} else {
					// Fallback: Extract license key from response if available (if snapshot extraction didn't work)
					$license_key_from_response = null;
					if ( isset( $response['data']['site']['licenseKey'] ) ) {
						$license_key_from_response = $response['data']['site']['licenseKey'];
					} elseif ( isset( $response['data']['licenseKey'] ) ) {
						$license_key_from_response = $response['data']['licenseKey'];
					} elseif ( isset( $response['data']['license']['licenseKey'] ) ) {
						$license_key_from_response = $response['data']['license']['licenseKey'];
					}

					// Store license key if we got it from the response and don't have one
					$current_key = $this->get_license_key();
					if ( ! empty( $license_key_from_response ) && empty( $current_key ) ) {
						$this->set_license_key( $license_key_from_response );
					}
				}

				// CRITICAL: Ensure usage is site-wide, not per-user
				// The backend should return the same usage for all users on the same site
				// If it doesn't, we need to ensure we're using site-based tracking
				// Pass the full response data as snapshot to preserve timestamps
				$full_snapshot = array_merge(
					$response['data']['site'] ?? array(),
					$response['data']['organization'] ?? array(),
					array(
						'licenseKey' => $response['data']['site']['licenseKey'] ?? $response['data']['licenseKey'] ?? $response['data']['license']['licenseKey'] ?? '',
						'createdAt'  => $response['data']['site']['createdAt'] ?? $response['data']['site']['created_at'] ?? $response['data']['createdAt'] ?? $response['data']['created_at'] ?? '',
						'updatedAt'  => $response['data']['site']['updatedAt'] ?? $response['data']['site']['updated_at'] ?? $response['data']['updatedAt'] ?? $response['data']['updated_at'] ?? '',
					)
				);
				$this->sync_license_usage_snapshot(
					$usage,
					$response['data']['organization'] ?? array(),
					$response['data']['site'] ?? array(),
					$full_snapshot
				);
			} else {
				// Update usage cache for non-license accounts
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $usage );
			}

			return $usage;
		}

		if ( $has_license && $license_cache ) {
			$cached_usage = $this->format_license_usage_from_cache( $license_cache );
			if ( $cached_usage ) {
				return $cached_usage;
			}
		}

		// For non-license accounts, try cached usage as fallback
		if ( ! $has_license ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );
			// Only use cached usage if it's a valid array with remaining credits >= 0
			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] >= 0 ) {
				// Return cached usage if available
				return $cached_usage;
			}
		}

		return new \WP_Error(
			'usage_failed',
			$response['data']['error'] ?? __( 'Failed to get usage info', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Get credit balance and subscription status
	 * Requires JWT authentication
	 *
	 * @return array|\WP_Error Returns credit balance and subscription info or WP_Error
	 */
	public function get_credit_balance() {
		return $this->usage_manager->get_credit_balance();
	}

	/**
	 * Get available credit packs
	 * Requires JWT authentication
	 *
	 * @return array|\WP_Error Returns array of credit packs or WP_Error
	 */
	public function get_credit_packs() {
		return $this->usage_manager->get_credit_packs();
	}

	/**
	 * Convert stored license data into a usage payload
	 */
	private function format_license_usage_from_cache( $license_data ) {
		if ( empty( $license_data ) || ! isset( $license_data['organization'] ) ) {
			return null;
		}

		$org           = $license_data['organization'];
			$limit     = isset( $org['tokenLimit'] ) ? intval( $org['tokenLimit'] ) : 10000;
			$remaining = isset( $org['tokensRemaining'] ) ? intval( $org['tokensRemaining'] ) : $limit;
			$used      = max( 0, $limit - $remaining );

			$reset_ts = 0;
		if ( ! empty( $org['resetDate'] ) ) {
			$reset_ts = strtotime( $org['resetDate'] );
		}
		if ( $reset_ts <= 0 ) {
			$reset_ts = strtotime( 'first day of next month' );
		}

			return array(
				'used'                => $used,
				'limit'               => $limit,
				'remaining'           => $remaining,
				'plan'                => strtolower( $org['plan'] ?? 'agency' ),
				'resetDate'           => $org['resetDate'] ?? '',
				'reset_timestamp'     => $reset_ts,
				'seconds_until_reset' => max( 0, $reset_ts - current_time( 'timestamp' ) ),
			);
	}

	/**
	 * Persist license usage snapshots so cached data stays in sync
	 */
	private function sync_license_usage_snapshot( $usage, $organization = array(), $site_data = array(), $snapshot = array() ) {
		$existing_license = $this->get_license_data();
		$updated_license  = is_array( $existing_license ) ? $existing_license : array();
		$org              = isset( $updated_license['organization'] ) && is_array( $updated_license['organization'] )
			? $updated_license['organization']
			: array();

		// Extract and store license key from site_data if available and we don't have one
		if ( ! empty( $site_data ) && is_array( $site_data ) && isset( $site_data['licenseKey'] ) && ! empty( $site_data['licenseKey'] ) ) {
			$current_key = $this->get_license_key();
			if ( empty( $current_key ) ) {
				$this->set_license_key( $site_data['licenseKey'] );
			}
		}

		if ( is_array( $organization ) && ! empty( $organization ) ) {
			$org = array_merge( $org, $organization );
		}

		if ( isset( $usage['limit'] ) ) {
			$org['tokenLimit'] = intval( $usage['limit'] );
		}

		if ( isset( $usage['remaining'] ) ) {
			$org['tokensRemaining'] = max( 0, intval( $usage['remaining'] ) );
		} elseif ( isset( $usage['used'] ) && isset( $org['tokenLimit'] ) ) {
			$org['tokensRemaining'] = max( 0, intval( $org['tokenLimit'] ) - intval( $usage['used'] ) );
		}

		if ( ! empty( $usage['resetDate'] ) ) {
			$org['resetDate'] = sanitize_text_field( $usage['resetDate'] );
		} elseif ( ! empty( $usage['nextReset'] ) ) {
			$org['resetDate'] = sanitize_text_field( $usage['nextReset'] );
		}

		if ( ! empty( $usage['plan'] ) ) {
			$org['plan'] = sanitize_text_field( $usage['plan'] );
		}

		$updated_license['organization'] = $org;

		if ( ! empty( $site_data ) ) {
			$updated_license['site'] = array_merge( $updated_license['site'] ?? array(), (array) $site_data );
		}

		// Preserve timestamps from API snapshot if available, otherwise use WordPress time
		if ( ! empty( $snapshot ) && is_array( $snapshot ) ) {
			// Preserve created_at from snapshot if available
			if ( ! empty( $snapshot['createdAt'] ) || ! empty( $snapshot['created_at'] ) ) {
				$updated_license['created_at'] = $snapshot['createdAt'] ?? $snapshot['created_at'] ?? '';
			} elseif ( empty( $updated_license['created_at'] ) ) {
				// Only set if we don't already have one
				$updated_license['created_at'] = current_time( 'mysql' );
			}
			// Preserve updated_at from snapshot if available
			if ( ! empty( $snapshot['updatedAt'] ) || ! empty( $snapshot['updated_at'] ) ) {
				$updated_license['updated_at'] = $snapshot['updatedAt'] ?? $snapshot['updated_at'] ?? '';
			} else {
				$updated_license['updated_at'] = current_time( 'mysql' );
			}
		} else {
			// Fallback: only update updated_at if we don't have created_at
			if ( empty( $updated_license['created_at'] ) ) {
				$updated_license['created_at'] = current_time( 'mysql' );
			}
			$updated_license['updated_at'] = current_time( 'mysql' );
		}

		$this->set_license_data( $updated_license );
	}

	/**
	 * Apply a license snapshot payload returned from the backend.
	 *
	 * @param array $snapshot License snapshot data.
	 */
	private function apply_license_snapshot( $snapshot ) {
		if ( empty( $snapshot ) || ! is_array( $snapshot ) ) {
			return;
		}

		if ( ! empty( $snapshot['licenseKey'] ) ) {
			$this->set_license_key( $snapshot['licenseKey'] );
		}

		$usage_payload = array(
			'limit'           => isset( $snapshot['tokenLimit'] ) ? intval( $snapshot['tokenLimit'] ) : 0,
			'remaining'       => isset( $snapshot['tokensRemaining'] ) ? intval( $snapshot['tokensRemaining'] ) : 0,
			'plan'            => isset( $snapshot['plan'] ) ? sanitize_text_field( $snapshot['plan'] ) : 'free',
			'resetDate'       => isset( $snapshot['resetDate'] ) ? sanitize_text_field( $snapshot['resetDate'] ) : '',
			'reset_timestamp' => isset( $snapshot['reset_timestamp'] ) ? intval( $snapshot['reset_timestamp'] ) : 0,
		);

		$organization = array(
			'plan'               => $usage_payload['plan'],
			'tokenLimit'         => $usage_payload['limit'],
			'tokensRemaining'    => $usage_payload['remaining'],
			'resetDate'          => $usage_payload['resetDate'],
			'reset_timestamp'    => $usage_payload['reset_timestamp'],
			'autoAttachStatus'   => isset( $snapshot['autoAttachStatus'] ) ? $snapshot['autoAttachStatus'] : '',
			'licenseEmailSentAt' => isset( $snapshot['licenseEmailSentAt'] ) ? $snapshot['licenseEmailSentAt'] : '',
		);

		$site_data = array(
			'siteUrl'            => isset( $snapshot['siteUrl'] ) ? $snapshot['siteUrl'] : '',
			'siteHash'           => isset( $snapshot['siteHash'] ) ? $snapshot['siteHash'] : '',
			'installId'          => isset( $snapshot['installId'] ) ? $snapshot['installId'] : '',
			'autoAttachStatus'   => isset( $snapshot['autoAttachStatus'] ) ? $snapshot['autoAttachStatus'] : '',
			'licenseKey'         => isset( $snapshot['licenseKey'] ) ? $snapshot['licenseKey'] : '',  // Store license key in site data too
			'licenseEmailSentAt' => isset( $snapshot['licenseEmailSentAt'] ) ? $snapshot['licenseEmailSentAt'] : '',
			// Preserve timestamps from API if available
			'created_at'         => isset( $snapshot['createdAt'] ) ? $snapshot['createdAt'] : ( isset( $snapshot['created_at'] ) ? $snapshot['created_at'] : '' ),
			'updated_at'         => isset( $snapshot['updatedAt'] ) ? $snapshot['updatedAt'] : ( isset( $snapshot['updated_at'] ) ? $snapshot['updated_at'] : '' ),
		);

		$this->sync_license_usage_snapshot( $usage_payload, $organization, $site_data, $snapshot );

		// Also store license key and timestamps in license data for easier retrieval
		if ( ! empty( $snapshot['licenseKey'] ) || ! empty( $snapshot['createdAt'] ) || ! empty( $snapshot['updatedAt'] ) ) {
			$license_data = $this->get_license_data();
			if ( empty( $license_data ) ) {
				$license_data = array();
			}
			if ( ! empty( $snapshot['licenseKey'] ) ) {
				$license_data['licenseKey'] = $snapshot['licenseKey'];
			}
			// Preserve timestamps from API
			if ( ! empty( $snapshot['createdAt'] ) || ! empty( $snapshot['created_at'] ) ) {
				$license_data['created_at'] = $snapshot['createdAt'] ?? $snapshot['created_at'] ?? '';
			}
			if ( ! empty( $snapshot['updatedAt'] ) || ! empty( $snapshot['updated_at'] ) ) {
				$license_data['updated_at'] = $snapshot['updatedAt'] ?? $snapshot['updated_at'] ?? '';
			}
			$this->set_license_data( $license_data );
		}

		if ( function_exists( 'opptiai_framework' ) ) {
			$framework = opptiai_framework();
			if ( $framework && isset( $framework->licensing ) && method_exists( $framework->licensing, 'sync_snapshot' ) ) {
				$framework_snapshot = $snapshot;
				if ( ! isset( $framework_snapshot['maskedLicenseKey'] ) && ! empty( $framework_snapshot['licenseKey'] ) ) {
					$framework_snapshot['maskedLicenseKey'] = $this->mask_license_key( $framework_snapshot['licenseKey'] );
				}
				unset( $framework_snapshot['licenseKey'] );
				$framework->licensing->sync_snapshot( $framework_snapshot );
			}
		}
	}

	/**
	 * Attempt to locate a license snapshot in an API response payload.
	 *
	 * @param array $data Response data array from make_request.
	 * @return array|null
	 */
	private function extract_license_snapshot( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( isset( $data['license'] ) && is_array( $data['license'] ) ) {
			return $data['license'];
		}

		if ( isset( $data['License'] ) && is_array( $data['License'] ) ) {
			return $data['License'];
		}

		if ( isset( $data['licenseSnapshot'] ) && is_array( $data['licenseSnapshot'] ) ) {
			return $data['licenseSnapshot'];
		}

		return null;
	}

	/**
	 * Mask license key for display/logging purposes.
	 *
	 * @param string $license_key Raw license key.
	 * @return string
	 */
	private function mask_license_key( $license_key ) {
		$license_key = (string) $license_key;
		if ( strlen( $license_key ) <= 8 ) {
			return str_repeat( '•', max( 0, strlen( $license_key ) - 4 ) ) . substr( $license_key, -4 );
		}

		return substr( $license_key, 0, 4 ) . str_repeat( '•', strlen( $license_key ) - 8 ) . substr( $license_key, -4 );
	}

	/**
	 * Check if user has reached their limit
	 */
	public function has_reached_limit() {
		return $this->usage_manager->has_reached_limit();
	}

	/**
	 * @deprecated Internal method - use Usage_Manager directly
	 */
	private function has_reached_limit_original() {
		if ( $this->has_active_license() ) {
			return false;
		}

		try {
			// ALWAYS check cached usage first - it's more reliable than API calls
			// This prevents false "limit reached" errors when API is slow/unreliable
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

			// If cached usage is valid and shows credits available, don't block
			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) ) {
				$remaining = intval( $cached_usage['remaining'] );
				// Only block if cached usage explicitly shows 0 remaining
				if ( $remaining > 0 ) {
					return false; // Cached shows credits available - don't block
				}
				if ( $remaining === 0 ) {
					return true; // Cached shows 0 - block generation
				}
			}

			// If no valid cached usage, try API as fallback
			$usage = $this->get_usage();

			// If there was an error getting usage, check if it's an auth error
			// For auth errors, don't assume limit reached - allow generation to proceed
			// Backend will handle usage limits during processing
			if ( is_wp_error( $usage ) ) {
				$error_code = $usage->get_error_code();

				// If it's an auth/user error, don't block generation - backend will handle it
				if ( $error_code === 'auth_required' || $error_code === 'user_not_found' ) {
					return false; // Don't block on auth errors
				}

				// For other errors and no cached usage, allow generation to proceed
				// Backend will enforce limits, and we don't want to block unnecessarily
				return false;
			}

			// Check if remaining is 0 or less from API response
			// Make sure remaining is numeric before comparing
			if ( isset( $usage['remaining'] ) && is_numeric( $usage['remaining'] ) ) {
				$remaining = intval( $usage['remaining'] );
				// Only block if API explicitly shows 0 remaining AND no cached usage contradicted it
				if ( $remaining === 0 && ( ! is_array( $cached_usage ) || ! isset( $cached_usage['remaining'] ) || intval( $cached_usage['remaining'] ) === 0 ) ) {
					return true; // API and cache both show 0 - block
				}
				if ( $remaining > 0 ) {
					return false; // API shows credits available - don't block
				}
			}

			// If we can't determine usage from API or cache, don't block - let backend handle it
			return false;
		} catch ( \Exception $e ) {
			// If anything throws an exception, check cached usage as fallback
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

			// Check if cached usage is valid and shows credits available
			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] > 0 ) {
				// Cached usage shows credits available - don't block
				return false;
			}

			// If cached usage shows 0 remaining, respect that and block
			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] === 0 ) {
				// Cached usage shows 0 credits - block generation
				return true;
			}

			// No valid cached usage available - don't block on exceptions, let backend handle it
			return false;
		}
	}

	/**
	 * Get percentage of quota used
	 */
	public function get_usage_percentage() {
		return $this->usage_manager->get_usage_percentage();
	}

	/**
	 * Generate alt text via API (Phase 2)
	 */
	public function generate_alt_text( $image_id, $context = array(), $regenerate = false ) {
		return $this->generation_client->generate_alt_text( $image_id, $context, $regenerate );
	}

	/**
	 * @deprecated Internal method - use Generation_Client directly
	 */
	private function generate_alt_text_original( $image_id, $context = array(), $regenerate = false ) {
		// Check if we have an active license first - if so, skip token validation
		$has_license = $this->has_active_license();

		// Validate authentication before making request
		// If we haven't checked recently and token exists, validate it first
		// BUT: Skip token validation if we have an active license (license-based auth works without token)
		if ( ! $has_license ) {
			$token = $this->get_token();
			if ( ! empty( $token ) && ! defined( 'WP_LOCAL_DEV' ) ) {
				$last_check = get_transient( 'bbai_token_last_check' );
				// If token check expired or never done, validate before generating
				if ( $last_check === false ) {
					$user_info = $this->get_user_info();
					if ( is_wp_error( $user_info ) ) {
						$error_code    = $user_info->get_error_code();
						$error_message = strtolower( $user_info->get_error_message() );

						// Only clear token if it's definitely invalid (not a temporary server error)
						// CRITICAL: Ensure error_message is a string before using strpos (PHP 8.1+ compatibility)
						$error_message_str = is_string( $error_message ) ? $error_message : '';
						if ( $error_code === 'auth_required' ||
							$error_code === 'user_not_found' ||
							( ! empty( $error_message_str ) && strpos( $error_message_str, 'user not found' ) !== false ) ||
							( ! empty( $error_message_str ) && strpos( $error_message_str, 'session expired' ) !== false ) ||
							( ! empty( $error_message_str ) && strpos( $error_message_str, 'unauthorized' ) !== false ) ) {
							// Token is definitely invalid, clear it
							$this->clear_token();
							delete_transient( 'bbai_token_last_check' );
							return new \WP_Error(
								'auth_required',
								__( 'Your session has expired. Please log in again.', 'beepbeep-ai-alt-text-generator' ),
								array( 'requires_auth' => true )
							);
						}
						// For server errors, network issues, etc., don't clear token
						// Just proceed with generation attempt - backend will handle it
					} else {
						// Token is valid, cache for 5 minutes
						set_transient( 'bbai_token_last_check', time(), 5 * MINUTE_IN_SECONDS );
					}
				}
			}

			// CRITICAL: If user is authenticated but has no license, try auto-attach
			// This handles the case where auto-attach failed during login/registration
			// or the user logged in before auto-attach was implemented
			if ( $this->is_authenticated() && ! $this->has_active_license() ) {
				// Check if we've already tried auto-attach recently (avoid spamming backend)
				$last_auto_attach = get_transient( 'bbai_last_auto_attach_attempt' );
				$should_retry     = $last_auto_attach === false || ( time() - $last_auto_attach ) > 300; // Retry every 5 minutes max

				if ( $should_retry ) {
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

					$auto_attach_result = $this->auto_attach_license();
					set_transient( 'bbai_last_auto_attach_attempt', time(), 300 ); // Cache attempt for 5 minutes

					if ( ! is_wp_error( $auto_attach_result ) ) {
						// Auto-attach succeeded, re-check license status
						if ( $this->has_active_license() ) {
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
							// License is now active, proceed with generation
						}
					} else {
						// Log auto-attach failure but don't block generation
						// Backend will return appropriate error if license is truly required
						$error_data      = $auto_attach_result->get_error_data();
						$is_schema_error = isset( $error_data['is_schema_error'] ) && $error_data['is_schema_error'];
						$log_level       = $is_schema_error ? 'warning' : 'warning';

						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							\BeepBeepAI\AltTextGenerator\Debug_Log::log(
								$log_level,
								'Auto-attach failed during generation (non-blocking)',
								array(
									'error'           => $auto_attach_result->get_error_message(),
									'is_schema_error' => $is_schema_error,
								),
								'licensing'
							);
						}
						// Continue with generation attempt - backend will handle the no-access error
					}
				}
			}
		}

		// Validate site fingerprint before credit operation
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
		$fingerprint_check = \BeepBeepAI\AltTextGenerator\Site_Fingerprint::check_on_credit_operation( false );
		if ( is_wp_error( $fingerprint_check ) ) {
			return $fingerprint_check;
		}

		$endpoint = 'api/generate';

		// CRITICAL: Log image_id being used for generation (to debug backend receiving wrong ID)
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			\BeepBeepAI\AltTextGenerator\Debug_Log::log(
				'warning',
				'generate_alt_text called',
				array(
					'image_id'   => $image_id,
					'regenerate' => $regenerate,
					'endpoint'   => $endpoint,
				),
				'api'
			);
		}

		// Enrich context with useful metadata for higher fidelity
		$image_url        = wp_get_attachment_url( $image_id );
		$title            = get_the_title( $image_id );
		$caption          = wp_get_attachment_caption( $image_id );
		$parsed_image_url = $image_url ? wp_parse_url( $image_url ) : null;
		$filename         = $parsed_image_url && isset( $parsed_image_url['path'] ) ? wp_basename( $parsed_image_url['path'] ) : '';

		// Debug: Log image details when regenerating to ensure correct image is being processed
		if ( $regenerate && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			// Get actual file path for verification
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

		// Check if image preparation failed due to size
		if ( isset( $image_payload['_error'] ) && $image_payload['_error'] === 'image_too_large' ) {
			return new \WP_Error(
				'image_too_large',
				$image_payload['_error_message'] ?? __( 'Image file is too large.', 'beepbeep-ai-alt-text-generator' ),
				array( 'image_id' => $image_id )
			);
		}

		// Check if image data is missing (critical error)
		if ( isset( $image_payload['_error'] ) && $image_payload['_error'] === 'missing_image_data' ) {
			return new \WP_Error(
				'missing_image_data',
				$image_payload['_error_message'] ?? __( 'Image data is missing. Cannot generate alt text.', 'beepbeep-ai-alt-text-generator' ),
				array( 'image_id' => $image_id )
			);
		}

		// Check if resize failed (critical error - cannot proceed with full-res image)
		if ( isset( $image_payload['_error'] ) && ( $image_payload['_error'] === 'resize_failed' || $image_payload['_error'] === 'editor_failed' ) ) {
			return new \WP_Error(
				$image_payload['_error'],
				$image_payload['_error_message'] ?? __( 'Failed to resize image. Cannot proceed to prevent high token costs.', 'beepbeep-ai-alt-text-generator' ),
				array( 
					'image_id' => $image_id,
					'code'     => $image_payload['_error'],
				)
			);
		}

		// Debug: Log what's being sent to backend (always, not just on regenerate)
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
					'image_url_full'      => ! empty( $image_payload['image_url'] ) ? $image_payload['image_url'] : 'none',
					'image_url_preview'   => ! empty( $image_payload['image_url'] ) ? substr( $image_payload['image_url'], 0, 100 ) . '...' : 'none',
					'base64_length'       => ! empty( $image_payload['image_base64'] ) ? strlen( $image_payload['image_base64'] ) : 0,
					'payload_dimensions'  => ( isset( $image_payload['width'] ) && isset( $image_payload['height'] ) ) ? $image_payload['width'] . 'x' . $image_payload['height'] : 'not set',
					'payload_keys'        => array_keys( $image_payload ),
					'image_id_in_payload' => ! empty( $image_payload['image_id'] ) ? $image_payload['image_id'] : 'missing',
				),
				'api'
			);
		}

		// CRITICAL: Ensure image data is actually included in payload
		// If image_url or image_base64 is missing, log a warning
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

		// CRITICAL: Ensure image_id in image_data payload matches the actual image_id
		// Backend might be reading image_id from image_data.image_id
		if ( isset( $image_payload['image_id'] ) && (string) $image_payload['image_id'] !== (string) $image_id ) {
			$payload_image_id_before   = $image_payload['image_id'] ?? 'missing';
			$image_payload['image_id'] = (string) $image_id;

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'Image ID mismatch detected and fixed',
					array(
						'expected_image_id'       => $image_id,
						'payload_image_id_before' => $payload_image_id_before,
						'payload_image_id_after'  => (string) $image_id,
					),
					'api'
				);
			}
		}

		// Ensure attachment_id mirrors image_id for backward compatibility with backend parsers
		if ( ! isset( $image_payload['attachment_id'] ) || (string) $image_payload['attachment_id'] !== (string) $image_id ) {
			$payload_attachment_before      = $image_payload['attachment_id'] ?? 'missing';
			$image_payload['attachment_id'] = (string) $image_id;

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Attachment ID normalized on payload',
					array(
						'expected_attachment_id'    => $image_id,
						'payload_attachment_before' => $payload_attachment_before,
						'payload_attachment_after'  => (string) $image_id,
					),
					'api'
				);
			}
		}

		// Get license key and site hash for usage tracking
		// CRITICAL: Retrieve license key BEFORE building body to ensure it's available
		$license_key = $this->get_license_key();
		$site_hash   = $this->get_site_id();

		// If no license key but we have an active license, try to get it
		// This ensures free users with auto-attached licenses have the key available
		if ( empty( $license_key ) && $this->has_active_license() ) {
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

			// Check license data directly first (fastest)
			$license_data = $this->get_license_data();
			if ( ! empty( $license_data ) ) {
				if ( isset( $license_data['licenseKey'] ) && ! empty( $license_data['licenseKey'] ) ) {
					$license_key = $license_data['licenseKey'];
					$this->set_license_key( $license_key );
				} elseif ( isset( $license_data['site']['licenseKey'] ) && ! empty( $license_data['site']['licenseKey'] ) ) {
					$license_key = $license_data['site']['licenseKey'];
					$this->set_license_key( $license_key );
				}
			}

			// If still empty, try to refresh license data from backend
			if ( empty( $license_key ) ) {
				$usage_response = $this->get_usage();
				if ( ! is_wp_error( $usage_response ) ) {
					$license_key = $this->get_license_key();
				}
			}

			// If still empty, try auto-attach
			if ( empty( $license_key ) ) {
				$auto_attach_result = $this->auto_attach_license();
				if ( ! is_wp_error( $auto_attach_result ) ) {
					$license_key = $this->get_license_key();
				}
			}

			// Final check - log if still missing
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

		$body = array(
			'image_data'    => $image_payload,
			'context'       => $context,
			'regenerate'    => $regenerate ? true : false, // Explicitly cast to boolean
			'service'       => 'alttext-ai', // Service identifier for backend (per backend API docs)
			// Add timestamp and image_id to prevent caching issues
			'timestamp'     => time(),
			'image_id'      => (string) $image_id, // Include image_id at root level AND in image_data
			'attachment_id' => (string) $image_id, // Redundant identifier for backend parsers
		);

		// CRITICAL: Include license key and site hash in body for backend usage tracking
		// Backend needs these to associate the request with the correct license/site for quota tracking
		if ( ! empty( $license_key ) ) {
			$body['licenseKey'] = $license_key;
		}
		if ( ! empty( $site_hash ) ) {
			$body['siteHash'] = $site_hash;
			$body['site_id']  = $site_hash; // Some backends might expect site_id
		}

		// Debug logging to verify license key is being sent (AFTER body is created with license key)
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
					'has_active_license'       => $this->has_active_license(),
					'license_key_in_body'      => ! empty( $body['licenseKey'] ),
					'site_hash_in_body'        => ! empty( $body['siteHash'] ),
				),
				'api'
			);
		}

		// Include user ID and explicit image identifiers in headers for traceability
		$extra_headers = array(
			'X-Image-ID'      => (string) $image_id,
			'X-Attachment-ID' => (string) $image_id,
		);

		$response = $this->request_with_retry( $endpoint, 'POST', $body, 3, true, $extra_headers );

		if ( is_wp_error( $response ) ) {
			// If we get a quota_check_mismatch error, the backend is reporting no credits
			// but our usage check shows credits available - this is a backend sync issue
			// Try one more time after a short delay to see if backend has synced
			if ( $response->get_error_code() === 'quota_check_mismatch' ) {
				$retry_after = $response->get_error_data()['retry_after'] ?? 3;

				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'info',
						'Quota mismatch detected - waiting before retry',
						array(
							'retry_after' => $retry_after,
							'image_id'    => $image_id,
						),
						'api'
					);
				}

				// Wait a moment for backend to sync
				sleep( $retry_after );

				// Retry the request once more
				$retry_response = $this->request_with_retry( $endpoint, 'POST', $body, 1, true, $extra_headers );

				if ( ! is_wp_error( $retry_response ) ) {
					// Retry succeeded - backend synced
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

				// Retry also failed - return the original error
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'warning',
						'Quota mismatch retry also failed',
						array(
							'image_id'    => $image_id,
							'retry_error' => $retry_response->get_error_message(),
						),
						'api'
					);
				}
			}

			return $response;
		}

		// Debug logging
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

		// Handle rate limit (429)
		// According to backend docs: 429 should only be for OpenAI rate limits (code: "OPENAI_RATE_LIMIT")
		// Quota issues should return 403 with NO_ACCESS code and reason: "no_credits"
		if ( $response['status_code'] === 429 ) {
			// Check if this is actually an OpenAI rate limit or a quota issue
			$response_data        = $response['data'] ?? array();
			$error_code           = isset( $response_data['code'] ) && is_string( $response_data['code'] ) ? strtoupper( $response_data['code'] ) : '';
			$is_openai_rate_limit = ( $error_code === 'OPENAI_RATE_LIMIT' ||
									( isset( $response_data['reason'] ) && strtolower( $response_data['reason'] ) === 'rate_limit_exceeded' ) );

			if ( $is_openai_rate_limit ) {
				// This is a legitimate OpenAI rate limit - return appropriate error
				return new \WP_Error(
					'openai_rate_limit',
					$response_data['message'] ?? __( 'OpenAI rate limit reached. Please try again later.', 'beepbeep-ai-alt-text-generator' ),
					array(
						'error_code'  => 'openai_rate_limit',
						'retry_after' => 60, // Wait 60 seconds for OpenAI rate limit
					)
				);
			}

			// If it's not an OpenAI rate limit, it might be a misconfigured backend
			// that's returning 429 for quota issues (should be 403)
			// IMPORTANT: Don't call get_usage() again here - we're already rate limited!
			// Instead, check cached usage and respect the rate limit
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
			$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

			// If cached usage shows credits available, trust the cache and return retry error
			// Don't make another API call - we're already rate limited!
			if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] > 0 ) {
				// Cached shows credits available - backend 429 might be incorrect or temporary rate limit
				// Return retry error with longer backoff to respect rate limits
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'warning',
						'Backend returned 429 (rate limit) but cache shows credits available - respecting rate limit',
						array(
							'cached_remaining' => $cached_usage['remaining'],
							'backend_error'    => $response_data['error'] ?? $response_data['message'] ?? 'Rate limit reached',
							'backend_code'     => $error_code,
							'endpoint'         => $response['endpoint'] ?? 'unknown',
						),
						'api'
					);
				}

				// Return a retry error with exponential backoff for rate limits
				$retry_after = isset( $response_data['retry_after'] ) ? intval( $response_data['retry_after'] ) : 10;
				return new \WP_Error(
					'rate_limit',
					__( 'Rate limit reached. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' ),
					array(
						'usage'       => $cached_usage,
						'retry_after' => $retry_after,
						'status_code' => 429,
					)
				);
			}

			// Cached usage confirms no credits OR fresh check also shows exhausted
			// OR it's a legitimate rate limit
			return new \WP_Error(
				'limit_reached',
				$response_data['message'] ?? $response_data['error'] ?? __( 'Rate limit reached. Please try again later.', 'beepbeep-ai-alt-text-generator' ),
				array( 'usage' => $response_data['usage'] ?? null )
			);
		}

		if ( ! $response['success'] ) {
			// Extract detailed error information
			$error_data    = $response['data'] ?? array();
			$error_message = $error_data['message'] ?? $error_data['error'] ?? __( 'Failed to generate alt text', 'beepbeep-ai-alt-text-generator' );
			$error_code    = $error_data['code'] ?? 'api_error';

			// Handle authentication/user errors - only clear token if definitely invalid
			// Don't clear on temporary server errors
			$error_message_lower = strtolower( $error_message . ' ' . ( $error_data['error'] ?? '' ) );
			$status_code_check   = isset( $response['status_code'] ) ? intval( $response['status_code'] ) : 0;

			// Only clear token if it's clearly an auth issue, not a server error
			if ( ( strpos( $error_message_lower, 'user not found' ) !== false ||
				strpos( $error_message_lower, 'user does not exist' ) !== false ||
				( $status_code_check === 401 && strpos( $error_message_lower, 'unauthorized' ) !== false ) ) &&
				$status_code_check < 500 ) { // Don't clear token on 500 errors - might be backend issue

				// User was deleted or token is invalid - clear stored credentials
				$this->clear_token();
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

			// Handle 413 Payload Too Large specifically
			if ( $response['status_code'] === 413 ) {
				$error_message = __( 'Image file is too large. Please compress or resize the image before generating alt text.', 'beepbeep-ai-alt-text-generator' );
				$error_code    = 'payload_too_large';
			}

			// Handle backend OpenAI API key errors (backend configuration issue)
			// Check both the error message and the backend's error field
			$backend_error_lower = strtolower( $error_message . ' ' . ( $error_data['error'] ?? '' ) );
			if ( strpos( $backend_error_lower, 'incorrect api key' ) !== false ||
				strpos( $backend_error_lower, 'invalid api key' ) !== false ||
				strpos( $backend_error_lower, 'api key provided' ) !== false ||
				$error_code === 'GENERATION_ERROR' ) {
				// This is a backend configuration issue - the backend's OpenAI API key is invalid/expired
				// This is NOT a plugin issue, but a backend server configuration problem
				$error_message = __( 'The backend service is experiencing a configuration issue. This is a temporary backend problem that needs to be fixed on the server side. Please try again in a few minutes or contact support if the issue persists.', 'beepbeep-ai-alt-text-generator' );
				$error_code    = 'backend_config_error';
			}

			// Log detailed error information for debugging
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

		return $response['data'];
	}

	/**
	 * Review existing alt text via API
	 */
	public function review_alt_text( $image_id, $alt_text, $context = array() ) {
		return $this->generation_client->review_alt_text( $image_id, $alt_text, $context );
	}

	/**
	 * Get billing information
	 */
	public function get_billing_info() {
		return $this->billing_client->get_billing_info();
	}

	/**
	 * Retrieve available plans (includes Stripe price IDs)
	 */
	public function get_plans() {
		return $this->billing_client->get_plans();
	}

	/**
	 * Create checkout session
	 */
	public function create_checkout_session( $price_id, $success_url, $cancel_url ) {
		return $this->billing_client->create_checkout_session( $price_id, $success_url, $cancel_url );
	}

	/**
	 * @deprecated Internal method - use Billing_Client directly
	 */
	private function create_checkout_session_original( $price_id, $success_url, $cancel_url ) {
		// For checkout, try without token if token is invalid/expired
		// This allows guest checkout - users can create account during checkout
		$token     = $this->get_token();
		$had_token = ! empty( $token );

		// First attempt: with token if available
		$response = $this->make_request(
			'/billing/checkout',
			'POST',
			array(
				'priceId'    => $price_id,
				'successUrl' => $success_url,
				'cancelUrl'  => $cancel_url,
			)
		);

		// Check response body for "user not found" errors (backend returns 500 with this message)
		$is_user_not_found_error = false;
		if ( is_wp_error( $response ) ) {
			$error_code    = $response->get_error_code();
			$error_message = strtolower( $response->get_error_message() );
			$error_data    = $response->get_error_data();

			// Check for "user not found" errors in various forms
			// CRITICAL: Ensure error_message is a string before using strpos (PHP 8.1+ compatibility)
			$error_message_str       = is_string( $error_message ) ? $error_message : '';
			$is_user_not_found_error = $error_code === 'user_not_found' ||
										( ! empty( $error_message_str ) && strpos( $error_message_str, 'user not found' ) !== false ) ||
										( ! empty( $error_message_str ) && strpos( $error_message_str, 'user does not exist' ) !== false ) ||
										( is_array( $error_data ) && isset( $error_data['code'] ) &&
										( $error_data['code'] === 'user_not_found' ||
										strpos( strtolower( $error_data['code'] ), 'user_not_found' ) !== false ) ) ||
										( is_array( $error_data ) && isset( $error_data['backend_message'] ) &&
										is_string( $error_data['backend_message'] ) &&
										strpos( strtolower( $error_data['backend_message'] ), 'user not found' ) !== false ) ||
										( is_array( $error_data ) && isset( $error_data['error_details'] ) &&
										is_string( $error_data['error_details'] ) &&
										strpos( strtolower( $error_data['error_details'] ), 'user not found' ) !== false );
		} elseif ( isset( $response['status_code'] ) && $response['status_code'] >= 500 ) {
			// Check response body for "user not found" in 500 errors
			$response_data = $response['data'] ?? array();
			$error_body    = '';
			if ( isset( $response_data['error'] ) && is_string( $response_data['error'] ) ) {
				$error_body = strtolower( $response_data['error'] );
			} elseif ( isset( $response_data['message'] ) && is_string( $response_data['message'] ) ) {
				$error_body = strtolower( $response_data['message'] );
			}
			$is_user_not_found_error = strpos( $error_body, 'user not found' ) !== false ||
										strpos( $error_body, 'user does not exist' ) !== false;
		}

		// If it's a "user not found" error and we had a token, clear token and retry without auth (for guest checkout)
		if ( $is_user_not_found_error && $had_token ) {
			// Clear invalid token - it's causing the backend to fail
			$this->clear_token();
			delete_transient( 'bbai_token_last_check' );

			// Retry without token (guest checkout)
			$response = $this->make_request(
				'/billing/checkout',
				'POST',
				array(
					'priceId'    => $price_id,
					'successUrl' => $success_url,
					'cancelUrl'  => $cancel_url,
				)
			);
		}

		// Handle errors - don't show "session expired" or "user not found" for checkout
		if ( is_wp_error( $response ) ) {
			$error_code    = $response->get_error_code();
			$error_message = $response->get_error_message();
			$error_lower   = strtolower( $error_message );
			$error_data    = $response->get_error_data();

			// For checkout, provide user-friendly error messages
			// Don't show technical errors like "user not found" or "session expired"
			if ( strpos( $error_lower, 'session' ) !== false ||
				strpos( $error_lower, 'log in' ) !== false ||
				strpos( $error_lower, 'authenticated' ) !== false ||
				strpos( $error_lower, 'user not found' ) !== false ||
				strpos( $error_lower, 'user does not exist' ) !== false ||
				$error_code === 'user_not_found' ||
				$error_code === 'auth_required' ||
				( is_array( $error_data ) && isset( $error_data['status_code'] ) && $error_data['status_code'] >= 500 ) ) {
				// For checkout errors, provide a helpful message
				return new \WP_Error(
					'checkout_failed',
					__( 'Unable to create checkout session. This may be a temporary backend issue. Please try again in a moment or contact support if the problem persists.', 'beepbeep-ai-alt-text-generator' ),
					array( 'response' => $error_data )
				);
			}
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'];
		}

		$error_message = '';
		if ( isset( $response['data']['error'] ) && is_string( $response['data']['error'] ) ) {
			$error_message = $response['data']['error'];
		} elseif ( isset( $response['data']['message'] ) && is_string( $response['data']['message'] ) ) {
			$error_message = $response['data']['message'];
		} elseif ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			$error_message = wp_json_encode( $response['data'] );
		}

		if ( ! $error_message ) {
			$error_message = __( 'Failed to create checkout session', 'beepbeep-ai-alt-text-generator' );
		}

		return new \WP_Error(
			'checkout_failed',
			$error_message,
			array( 'response' => $response )
		);
	}

	/**
	 * Create customer portal session
	 */
	public function create_customer_portal_session( $return_url ) {
		return $this->billing_client->create_customer_portal_session( $return_url );
	}

	/**
	 * Request password reset
	 */
	public function forgot_password( $email ) {
		return $this->auth_manager->forgot_password( $email );
	}

	/**
	 * Reset password with token
	 */
	public function reset_password( $email, $token, $new_password ) {
		return $this->auth_manager->reset_password( $email, $token, $new_password );
	}

	/**
	 * Get subscription information
	 */
	public function get_subscription_info() {
		return $this->billing_client->get_subscription_info();
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
	private function prepare_image_payload( $image_id, $image_url, $title, $caption, $filename ) {
		$payload = array(
			'image_id'      => (string) $image_id,  // Cast to string for Prisma compatibility
			'attachment_id' => (string) $image_id, // Redundant field for backend compatibility
			'title'         => $title,
			'caption'       => $caption,
			'filename'      => $filename,
		);
		
		// Initialize image_url as empty - we'll only set it if we can't send base64
		// This ensures backend uses our optimized base64 instead of downloading full-res from URL

		if ( $image_url ) {
			// ALWAYS resize and send as base64 to optimize token costs
			// Backend downloads full-resolution images from URLs, causing high token usage
			// By resizing and sending base64, we control the exact resolution sent to OpenAI
			$file_path = get_attached_file( $image_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );
				$max_file_size = 512 * 1024; // 512KB - resize if larger

				// Get metadata first
				$mime_type = get_post_mime_type( $image_id ) ?: 'image/jpeg';

				// CRITICAL: Always check ACTUAL file dimensions first, not metadata
				// Metadata can be outdated or incorrect (e.g., shows 900x600 but file is 1164x1473)
				$image_info = getimagesize( $file_path );
				$orig_width  = $image_info ? $image_info[0] : 0;
				$orig_height = $image_info ? $image_info[1] : 0;
				
				// Fallback to metadata only if getimagesize fails
				if ( empty( $orig_width ) || empty( $orig_height ) ) {
					$metadata = wp_get_attachment_metadata( $image_id );
					$orig_width  = $metadata['width'] ?? 0;
					$orig_height = $metadata['height'] ?? 0;
				}

				// Target max dimension for OpenAI Vision API (1024px = ~170 tokens, 512px = ~85 tokens)
				// ALWAYS resize to optimize token costs, even if image is already small
				// Use 512px max dimension for optimal cost savings (50% token reduction, no quality loss)
				// This balances token cost optimization with backend size requirements
				$max_dimension = apply_filters( 'bbai_vision_api_max_dimension', 512, $image_id, $file_path );
				$max_dimension = max( 256, min( 2048, intval( $max_dimension ) ) ); // Clamp between 256-2048px

				// ALWAYS resize images larger than max_dimension to optimize token costs
				// This ensures we control the exact resolution sent to OpenAI
				// Backend may download full-res images from URLs, causing high token usage
				$needs_resize = ( $orig_width > $max_dimension || $orig_height > $max_dimension );
				// CRITICAL: If resize is needed, we MUST resize - never encode original if > max_dimension
				$should_resize = $needs_resize && function_exists( 'wp_get_image_editor' ) && 
								( $orig_width > 0 && $orig_height > 0 );

				// Debug logging for resize decision
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'info',
						'Image resize check',
						array(
							'image_id'      => $image_id,
							'orig_width'    => $orig_width,
							'orig_height'   => $orig_height,
							'max_dimension' => $max_dimension,
							'needs_resize'  => $needs_resize,
							'should_resize' => $should_resize,
							'file_size_kb'  => round( $file_size / 1024, 1 ),
						),
						'api'
					);
				}

				if ( $should_resize ) {
					// Calculate new dimensions maintaining aspect ratio
					$ratio = min( $max_dimension / $orig_width, $max_dimension / $orig_height );
					$new_width  = intval( $orig_width * $ratio );
					$new_height = intval( $orig_height * $ratio );

					// Debug logging for resize operation
					if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'info',
							'Resizing image before encoding',
							array(
								'image_id'    => $image_id,
								'from'        => $orig_width . 'x' . $orig_height,
								'to'          => $new_width . 'x' . $new_height,
								'max_dim'     => $max_dimension,
							),
							'api'
						);
					}

					$editor = wp_get_image_editor( $file_path );
					if ( ! is_wp_error( $editor ) ) {
						$editor->resize( $new_width, $new_height, false );

						// Set quality to reduce file size - use lower quality for token optimization
						// Quality 57 is optimized to meet backend size requirements (~63KB base64 max)
						if ( method_exists( $editor, 'set_quality' ) ) {
							$editor->set_quality( 57 );
						}

						$upload_dir    = wp_upload_dir();
						$temp_filename = 'beepbeepai-temp-' . $image_id . '-' . time() . '.jpg';
						$temp_path     = $upload_dir['path'] . '/' . $temp_filename;
						$saved         = $editor->save( $temp_path, 'image/jpeg' );

						if ( ! is_wp_error( $saved ) && isset( $saved['path'] ) ) {
							// Verify the resized image dimensions
							$resized_info = getimagesize( $saved['path'] );
							$actual_resized_width  = $resized_info ? $resized_info[0] : 0;
							$actual_resized_height = $resized_info ? $resized_info[1] : 0;
							
							// Debug logging
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'info',
									'Resized image saved',
									array(
										'image_id'            => $image_id,
										'expected'            => $new_width . 'x' . $new_height,
										'actual'              => $actual_resized_width . 'x' . $actual_resized_height,
										'resized_file_size_kb' => round( filesize( $saved['path'] ) / 1024, 1 ),
									),
									'api'
								);
							}
							
							$resized_contents = file_get_contents( $saved['path'] );
							@unlink( $saved['path'] );
							if ( $resized_contents !== false ) {
								$base64 = base64_encode( $resized_contents );
								if ( strlen( $base64 ) <= 5.5 * 1024 * 1024 ) {
									// CRITICAL: Update payload dimensions to match resized image (not original)
									$payload_width  = $actual_resized_width > 0 ? $actual_resized_width : $new_width;
									$payload_height = $actual_resized_height > 0 ? $actual_resized_height : $new_height;
									
									// Validate base64 size matches reported dimensions (reject gray zone cases)
									if ( $this->validate_base64_size( $base64, $payload_width, $payload_height ) ) {
										$payload['image_base64'] = $base64;
										$payload['mime_type']    = 'image/jpeg';
										$payload['width']        = $payload_width;
										$payload['height']       = $payload_height;
										// CRITICAL: Remove image_url when we have base64 to prevent backend from downloading full-res
										unset( $payload['image_url'] );
									
										// Debug logging
										if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
											\BeepBeepAI\AltTextGenerator\Debug_Log::log(
												'info',
												'Encoding resized image to base64',
												array(
													'image_id'         => $image_id,
													'payload_dimensions' => $payload['width'] . 'x' . $payload['height'],
													'base64_length'    => strlen( $base64 ),
												),
												'api'
											);
										}
									} else {
										// Base64 too small for dimensions (gray zone) - reject and use URL
										if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
											\BeepBeepAI\AltTextGenerator\Debug_Log::log(
												'warning',
												'Rejecting base64: size too small for dimensions, using URL instead',
												array(
													'image_id'         => $image_id,
													'dimensions'       => $payload_width . 'x' . $payload_height,
													'base64_size_kb'   => round( strlen( $base64 ) / 1024, 2 ),
												),
												'api'
											);
										}
										unset( $payload['image_base64'] );
										$payload['image_url'] = $image_url;
									}
								} else {
									// Still too large even after resize - send URL as fallback
									unset( $payload['image_base64'] );
									$payload['image_url'] = $image_url;
								}
							} else {
								$payload['image_url'] = $image_url;
							}
						} else {
							// Resize failed - CRITICAL: Do NOT fall back to original file or URL
							// This would cause high token costs. Return error instead.
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'error',
									'Image resize failed - cannot encode original file',
									array(
										'image_id'     => $image_id,
										'error'        => is_wp_error( $saved ) ? $saved->get_error_message() : 'Unknown error',
										'original_dim' => $orig_width . 'x' . $orig_height,
										'target_dim'   => $new_width . 'x' . $new_height,
									),
									'api'
								);
							}
							// Return error - do NOT send original file or URL as it would cause high token costs
							$payload['_error'] = 'resize_failed';
							$payload['_error_message'] = __( 'Failed to resize image. Cannot proceed with full-resolution image to prevent high token costs.', 'beepbeep-ai-alt-text-generator' );
							unset( $payload['image_url'] );
							unset( $payload['image_base64'] );
						}
					} else {
						// Editor creation failed - CRITICAL: Do NOT fall back to original file or URL
						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							\BeepBeepAI\AltTextGenerator\Debug_Log::log(
								'error',
								'Image editor creation failed - cannot encode original file',
								array(
									'image_id'     => $image_id,
									'error'        => is_wp_error( $editor ) ? $editor->get_error_message() : 'Unknown error',
									'original_dim' => $orig_width . 'x' . $orig_height,
									'target_dim'   => $new_width . 'x' . $new_height,
								),
								'api'
							);
						}
						// Return error - do NOT send original file or URL as it would cause high token costs
						$payload['_error'] = 'editor_failed';
						$payload['_error_message'] = __( 'Failed to create image editor. Cannot proceed with full-resolution image to prevent high token costs.', 'beepbeep-ai-alt-text-generator' );
						unset( $payload['image_url'] );
						unset( $payload['image_base64'] );
					}
				} else {
					// Image metadata says it's at or below max_dimension, but we need to verify actual file dimensions
					// CRITICAL: Always check actual file dimensions, not just metadata, as metadata may be outdated
					// Even if metadata says 900x600, the actual file might be full-res
					$actual_image_info = getimagesize( $file_path );
					$actual_width  = $actual_image_info ? $actual_image_info[0] : $orig_width;
					$actual_height = $actual_image_info ? $actual_image_info[1] : $orig_height;
					
					// Update orig_width/height to actual dimensions for consistency
					$orig_width  = $actual_width;
					$orig_height = $actual_height;
					
					// If actual file is larger than max_dimension, resize it before encoding
					if ( $actual_width > $max_dimension || $actual_height > $max_dimension ) {
						// File is actually larger than metadata suggests - resize it
						$ratio = min( $max_dimension / $actual_width, $max_dimension / $actual_height );
						$new_width  = intval( $actual_width * $ratio );
						$new_height = intval( $actual_height * $ratio );

						$editor = wp_get_image_editor( $file_path );
						if ( ! is_wp_error( $editor ) ) {
							$editor->resize( $new_width, $new_height, false );
							// Set quality to reduce file size - use lower quality for token optimization
							if ( method_exists( $editor, 'set_quality' ) ) {
								$editor->set_quality( 75 );
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
										// Validate base64 size matches reported dimensions (reject gray zone cases)
										if ( $this->validate_base64_size( $base64, $new_width, $new_height ) ) {
											$payload['image_base64'] = $base64;
											$payload['mime_type']    = 'image/jpeg';
											// Update payload dimensions to match resized image
											$payload['width']  = $new_width;
											$payload['height'] = $new_height;
											// CRITICAL: Remove image_url when we have base64
											unset( $payload['image_url'] );
										} else {
											// Base64 too small for dimensions (gray zone) - reject and use URL
											if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
												\BeepBeepAI\AltTextGenerator\Debug_Log::log(
													'warning',
													'Rejecting base64: size too small for dimensions, using URL instead',
													array(
														'image_id'       => $image_id,
														'dimensions'     => $new_width . 'x' . $new_height,
														'base64_size_kb' => round( strlen( $base64 ) / 1024, 2 ),
													),
													'api'
												);
											}
											unset( $payload['image_base64'] );
											$payload['image_url'] = $image_url;
										}
									} else {
										// Still too large even after resize - send URL as fallback
										unset( $payload['image_base64'] );
										$payload['image_url'] = $image_url;
									}
								} else {
									unset( $payload['image_base64'] );
									$payload['image_url'] = $image_url;
								}
							} else {
								unset( $payload['image_base64'] );
								$payload['image_url'] = $image_url;
							}
						} else {
							unset( $payload['image_base64'] );
							$payload['image_url'] = $image_url;
						}
					} else {
						// Actual file dimensions are at or below max_dimension - safe to encode directly
						// But still update payload dimensions to actual file dimensions (not metadata)
						if ( $file_size > 0 && $file_size <= 5.5 * 1024 * 1024 ) {
							$contents = file_get_contents( $file_path );
							if ( $contents !== false ) {
								$base64 = base64_encode( $contents );
								if ( strlen( $base64 ) <= 5.5 * 1024 * 1024 ) {
									// Validate base64 size matches reported dimensions (reject gray zone cases)
									if ( $this->validate_base64_size( $base64, $actual_width, $actual_height ) ) {
										$payload['image_base64'] = $base64;
										$payload['mime_type']    = $mime_type;
										// CRITICAL: Set payload dimensions to actual file dimensions (not metadata)
										$payload['width']  = $actual_width;
										$payload['height'] = $actual_height;
										// CRITICAL: Remove image_url when we have base64
										unset( $payload['image_url'] );
									} else {
										// Base64 too small for dimensions (gray zone) - reject and use URL
										if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
											\BeepBeepAI\AltTextGenerator\Debug_Log::log(
												'warning',
												'Rejecting base64: size too small for dimensions, using URL instead',
												array(
													'image_id'       => $image_id,
													'dimensions'     => $actual_width . 'x' . $actual_height,
													'base64_size_kb' => round( strlen( $base64 ) / 1024, 2 ),
												),
												'api'
											);
										}
										unset( $payload['image_base64'] );
										$payload['image_url'] = $image_url;
									}
								} else {
									// Base64 too large - use URL
									unset( $payload['image_base64'] );
									$payload['image_url'] = $image_url;
								}
							} else {
								unset( $payload['image_base64'] );
								$payload['image_url'] = $image_url;
							}
						} else {
							unset( $payload['image_base64'] );
							$payload['image_url'] = $image_url;
						}
					}
				}
			} else {
				// Fallback to URL if file doesn't exist
				$payload['image_url'] = $image_url;
			}
		} else {
			// No image URL provided - this is an error condition
			// Log it but still return the payload (backend should handle the error)
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
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
		}

		// Only add base64 if we don't already have it AND we don't have a URL set
		// This prevents sending both URL and base64, which could cause backend to use full-res URL
		$file_path = $file_path ?? get_attached_file( $image_id );
		if ( empty( $payload['image_base64'] ) && empty( $payload['image_url'] ) && $file_path && file_exists( $file_path ) ) {
			$file_size       = filesize( $file_path );
			$max_inline_size = 5.5 * 1024 * 1024; // ~5.5MB upper bound
			if ( $file_size > 0 && $file_size <= $max_inline_size ) {
				// CRITICAL: Always check ACTUAL file dimensions first, not metadata
				// Metadata can be outdated (e.g., shows 900x600 but file is 1164x1473)
				$image_info = getimagesize( $file_path );
				$orig_width  = $image_info ? $image_info[0] : 0;
				$orig_height = $image_info ? $image_info[1] : 0;
				
				// Fallback to metadata only if getimagesize fails
				if ( empty( $orig_width ) || empty( $orig_height ) ) {
					$metadata = wp_get_attachment_metadata( $image_id );
					$orig_width  = $metadata['width'] ?? 0;
					$orig_height = $metadata['height'] ?? 0;
				}

				// Resize to optimize token costs before encoding
				// Use 512px max dimension for optimal cost savings (50% token reduction, no quality loss)
				$max_dimension = apply_filters( 'bbai_vision_api_max_dimension', 512, $image_id, $file_path );
				$max_dimension = max( 256, min( 2048, intval( $max_dimension ) ) );
				
				// CRITICAL: If resize is needed, we MUST resize - never encode original
				$needs_resize = ( $orig_width > $max_dimension || $orig_height > $max_dimension );
				$can_resize = function_exists( 'wp_get_image_editor' ) && ( $orig_width > 0 && $orig_height > 0 );

				if ( $needs_resize && $can_resize ) {
					$ratio = min( $max_dimension / $orig_width, $max_dimension / $orig_height );
					$new_width  = intval( $orig_width * $ratio );
					$new_height = intval( $orig_height * $ratio );

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
							$contents = file_get_contents( $saved['path'] );
							@unlink( $saved['path'] );
							// CRITICAL: Update payload dimensions to match resized image
							$payload['width']  = $new_width;
							$payload['height'] = $new_height;
						} else {
							// Resize failed - do NOT encode original file
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'error',
									'Fallback resize failed - cannot encode original',
									array(
										'image_id' => $image_id,
										'error'    => is_wp_error( $saved ) ? $saved->get_error_message() : 'Unknown error',
									),
									'api'
								);
							}
							$contents = false;
						}
					} else {
						// Editor failed - do NOT encode original file
						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							\BeepBeepAI\AltTextGenerator\Debug_Log::log(
								'error',
								'Fallback editor creation failed - cannot encode original',
								array(
									'image_id' => $image_id,
									'error'    => is_wp_error( $editor ) ? $editor->get_error_message() : 'Unknown error',
								),
								'api'
							);
						}
						$contents = false;
					}
				} elseif ( $needs_resize && ! $can_resize ) {
					// Resize needed but editor not available - do NOT encode original
					if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
						\BeepBeepAI\AltTextGenerator\Debug_Log::log(
							'error',
							'Resize needed but wp_get_image_editor not available - cannot encode original',
							array(
								'image_id'     => $image_id,
								'original_dim' => $orig_width . 'x' . $orig_height,
							),
							'api'
						);
					}
					$contents = false;
				} else {
					// No resize needed - safe to encode original file
					// But verify the file is complete and valid before encoding
					$contents = file_get_contents( $file_path );
					if ( $contents === false || strlen( $contents ) === 0 ) {
						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							\BeepBeepAI\AltTextGenerator\Debug_Log::log(
								'error',
								'Failed to read file contents for base64 encoding',
								array(
									'image_id' => $image_id,
									'file_path' => $file_path,
								),
								'api'
							);
						}
						$contents = false;
					} else {
						// Verify the file is a valid image by checking if it can be decoded
						$test_decode = @imagecreatefromstring( $contents );
						if ( $test_decode === false ) {
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'error',
									'File contents are not a valid image',
									array(
										'image_id' => $image_id,
										'file_path' => $file_path,
										'file_size' => strlen( $contents ),
									),
									'api'
								);
							}
							$contents = false;
						} else {
							imagedestroy( $test_decode );
						}
					}
					// Set payload dimensions to actual file dimensions
					$payload['width']  = $orig_width;
					$payload['height'] = $orig_height;
				}

				if ( $contents !== false ) {
					$base64 = base64_encode( $contents );
					// Avoid sending absurdly large base64 (should align with size check)
					if ( ! empty( $base64 ) && strlen( $base64 ) <= $max_inline_size * 1.4 ) {
						// Validate base64 size matches reported dimensions (reject gray zone cases)
						// Use payload dimensions if set, otherwise use orig dimensions
						$validate_width  = $payload['width'] ?? $orig_width;
						$validate_height = $payload['height'] ?? $orig_height;
						
						if ( $this->validate_base64_size( $base64, $validate_width, $validate_height ) ) {
							$mime_type               = $mime_type ?? get_post_mime_type( $image_id ) ?: 'image/jpeg';
							$payload['image_base64'] = $base64;
							$payload['mime_type']    = $mime_type;
							// CRITICAL: Remove image_url when we have base64
							unset( $payload['image_url'] );
						} else {
							// Base64 too small for dimensions (gray zone) - reject
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'warning',
									'Rejecting base64: size too small for dimensions',
									array(
										'image_id'       => $image_id,
										'dimensions'     => $validate_width . 'x' . $validate_height,
										'base64_size_kb' => round( strlen( $base64 ) / 1024, 2 ),
									),
									'api'
								);
							}
							// Don't set image_base64, and ensure we have image_url as fallback
							if ( ! empty( $image_url ) ) {
								$payload['image_url'] = $image_url;
							}
						}
					}
				}
			}
		}

		// CRITICAL: Ensure we never send both URL and base64
		// Backend now prioritizes image_base64 over image_url (commit 13adf7c)
		// Still removing URL when base64 is present to avoid confusion and reduce payload size
		if ( ! empty( $payload['image_base64'] ) ) {
			unset( $payload['image_url'] );
		}

		// CRITICAL: Verify that image data is included before returning
		// At least one of image_url or image_base64 must be present
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
			// Set error flag so caller knows payload is invalid
			$payload['_error']         = 'missing_image_data';
			$payload['_error_message'] = 'Image URL or base64 data is required';
		}

		return $payload;
	}

	/**
	 * Sanitize context data before logging to prevent exposing sensitive information
	 */
	private function sanitize_log_context( $context ) {
		if ( ! is_array( $context ) ) {
			return $context;
		}

		$sanitized      = array();
		$sensitive_keys = array( 'url', 'token', 'api_key', 'password', 'secret', 'authorization', 'auth', 'bearer' );

		foreach ( $context as $key => $value ) {
			$key_lower = strtolower( $key );

			// Skip sensitive keys entirely
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $key_lower, $sensitive ) !== false ) {
					$sanitized[ $key ] = '[REDACTED]';
					continue 2;
				}
			}

			// Sanitize URLs - only show endpoint path, not full URL
			if ( $key === 'url' && is_string( $value ) ) {
				// Extract just the endpoint path, remove base URL
				$parsed = wp_parse_url( $value );
				if ( isset( $parsed['path'] ) ) {
					$sanitized[ $key ] = $parsed['path'] . ( isset( $parsed['query'] ) ? '?' . '[QUERY_PARAMS]' : '' );
				} else {
					$sanitized[ $key ] = '[REDACTED_URL]';
				}
			}
			// Sanitize endpoint - remove query parameters
			elseif ( $key === 'endpoint' && is_string( $value ) ) {
				$sanitized[ $key ] = strtok( $value, '?' );
			}
			// Sanitize error messages - remove potential sensitive data
			elseif ( $key === 'error' && is_string( $value ) ) {
				// Remove any URLs, tokens, or API keys from error messages
				$sanitized[ $key ] = preg_replace(
					array(
						'/https?:\/\/[^\s]+/i',  // Remove URLs
						'/Bearer\s+[A-Za-z0-9\-_]+/i',  // Remove Bearer tokens
						'/token[=:]\s*[A-Za-z0-9\-_]+/i',  // Remove token values
						'/api[_-]?key[=:]\s*[A-Za-z0-9\-_]+/i',  // Remove API keys
					),
					'[REDACTED]',
					$value
				);
			}
			// Recursively sanitize nested arrays
			elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_log_context( $value );
			}
			// Keep other values as-is
			else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	public function log_api_event( $level, $message, $context = array() ) {
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			// Sanitize context before logging
			$sanitized_context = $this->sanitize_log_context( $context );
			\BeepBeepAI\AltTextGenerator\Debug_Log::log( $level, $message, $sanitized_context, 'api' );
		}
	}
}
