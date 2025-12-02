<?php
/**
 * Auth Manager
 * Handles authentication, user management, and auth-related API endpoints
 */

namespace BeepBeepAI\AltTextGenerator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Auth_Manager {

	private $request_handler;
	private $get_usage_callback;
	private $auto_attach_license_callback;
	private $extract_license_snapshot_callback;
	private $apply_license_snapshot_callback;
	private $has_active_license_callback;
	private $get_site_id_callback;
	private $get_token_callback;
	private $set_token_callback;
	private $clear_token_callback;
	private $get_user_data_callback;
	private $set_user_data_callback;
	private $is_authenticated_callback;

	public function __construct( $request_handler, $callbacks = array() ) {
		$this->request_handler                   = $request_handler;
		$this->get_usage_callback                = $callbacks['get_usage'] ?? null;
		$this->auto_attach_license_callback      = $callbacks['auto_attach_license'] ?? null;
		$this->extract_license_snapshot_callback = $callbacks['extract_license_snapshot'] ?? null;
		$this->apply_license_snapshot_callback   = $callbacks['apply_license_snapshot'] ?? null;
		$this->has_active_license_callback       = $callbacks['has_active_license'] ?? null;
		$this->get_site_id_callback              = $callbacks['get_site_id'] ?? null;
		$this->get_token_callback                = $callbacks['get_token'] ?? null;
		$this->set_token_callback                = $callbacks['set_token'] ?? null;
		$this->clear_token_callback              = $callbacks['clear_token'] ?? null;
		$this->get_user_data_callback            = $callbacks['get_user_data'] ?? null;
		$this->set_user_data_callback            = $callbacks['set_user_data'] ?? null;
		$this->is_authenticated_callback         = $callbacks['is_authenticated'] ?? null;
	}

	/**
	 * Register new user
	 */
	public function register( $email, $password ) {
		// Check if site already has an account
		$existing_token = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';
		if ( ! empty( $existing_token ) ) {
			// Site already has an account - check if it's a free plan
			$usage = $this->get_usage_callback ? call_user_func( $this->get_usage_callback ) : null;
			if ( ! is_wp_error( $usage ) && isset( $usage['plan'] ) && $usage['plan'] === 'free' ) {
				return new \WP_Error(
					'free_plan_exists',
					__( 'A free plan has already been used for this site. Upgrade to Pro or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator' ),
					array( 'code' => 'free_plan_already_used' )
				);
			}
		}

		// Get site identifier
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();

		$response = $this->request_handler->make_request(
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
			$error_message = strtolower( $response->get_error_message() );
			if ( strpos( $error_message, 'free plan' ) !== false ||
				strpos( $error_message, 'already used' ) !== false ||
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
			if ( $this->set_token_callback ) {
				call_user_func( $this->set_token_callback, $response['data']['token'] );
			}
			if ( isset( $response['data']['user'] ) && $this->set_user_data_callback ) {
				call_user_func( $this->set_user_data_callback, $response['data']['user'] );
			}

			// Extract and apply license snapshot
			if ( $this->extract_license_snapshot_callback && $this->apply_license_snapshot_callback ) {
				$snapshot = call_user_func( $this->extract_license_snapshot_callback, $response['data'] );
				if ( $snapshot ) {
					call_user_func( $this->apply_license_snapshot_callback, $snapshot );
				} else {
					// Try auto-attach for free users
					if ( ! $this->has_active_license_callback || ! call_user_func( $this->has_active_license_callback ) ) {
						if ( $this->auto_attach_license_callback ) {
							$auto_attach_result = call_user_func( $this->auto_attach_license_callback );
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
	 */
	public function login( $email, $password ) {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();

		$response = $this->request_handler->make_request(
			'/auth/login',
			'POST',
			array(
				'email'    => $email,
				'password' => $password,
				'site_id'  => $site_id,
				'site_url' => get_site_url(),
			),
			null,
			false,
			array(),
			false
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] && isset( $response['data']['token'] ) ) {
			if ( $this->set_token_callback ) {
				call_user_func( $this->set_token_callback, $response['data']['token'] );
			}
			if ( isset( $response['data']['user'] ) && $this->set_user_data_callback ) {
				call_user_func( $this->set_user_data_callback, $response['data']['user'] );
			}

			// Extract and apply license snapshot
			if ( $this->extract_license_snapshot_callback && $this->apply_license_snapshot_callback ) {
				$snapshot = call_user_func( $this->extract_license_snapshot_callback, $response['data'] );
				if ( $snapshot ) {
					call_user_func( $this->apply_license_snapshot_callback, $snapshot );
				} else {
					// Try auto-attach for free users
					if ( ! $this->has_active_license_callback || ! call_user_func( $this->has_active_license_callback ) ) {
						if ( $this->auto_attach_license_callback ) {
							$auto_attach_result = call_user_func( $this->auto_attach_license_callback );
							if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								if ( is_wp_error( $auto_attach_result ) ) {
									\BeepBeepAI\AltTextGenerator\Debug_Log::log(
										'warning',
										'Auto-attach license failed during login (non-blocking)',
										array(
											'error' => $auto_attach_result->get_error_message(),
										),
										'licensing'
									);
								} else {
									\BeepBeepAI\AltTextGenerator\Debug_Log::log(
										'info',
										'Auto-attach license succeeded during login',
										array(
											'plan' => $auto_attach_result['license']['plan'] ?? 'free',
										),
										'licensing'
									);
								}
							}
						}
					}
				}
			}

			return $response['data'];
		}

		return new \WP_Error(
			'login_failed',
			$response['data']['error'] ?? __( 'Login failed', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Get current user info
	 */
	public function get_user_info() {
		$response = $this->request_handler->make_request( '/auth/me' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			if ( isset( $response['data']['user'] ) && $this->set_user_data_callback ) {
				call_user_func( $this->set_user_data_callback, $response['data']['user'] );
			}

			// Extract and apply license snapshot
			if ( $this->extract_license_snapshot_callback && $this->apply_license_snapshot_callback ) {
				$snapshot = call_user_func( $this->extract_license_snapshot_callback, $response['data'] );
				if ( $snapshot ) {
					call_user_func( $this->apply_license_snapshot_callback, $snapshot );
				} else {
					// Try auto-attach for free users
					if ( ! $this->has_active_license_callback || ! call_user_func( $this->has_active_license_callback ) ) {
						if ( $this->auto_attach_license_callback ) {
							$auto_attach_result = call_user_func( $this->auto_attach_license_callback );
							if ( is_wp_error( $auto_attach_result ) && class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
								\BeepBeepAI\AltTextGenerator\Debug_Log::log(
									'warning',
									'Auto-attach license failed during get_user_info',
									array(
										'error' => $auto_attach_result->get_error_message(),
									),
									'licensing'
								);
							}
						}
					}
				}
			}

			// Sync identity after token refresh
			$this->sync_identity();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[BeepBeep AI] JWT token refresh succeeded via get_user_info()' );
			}

			return $response['data']['user'];
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$error_msg = $response['data']['error'] ?? __( 'Failed to get user info', 'beepbeep-ai-alt-text-generator' );
			error_log( sprintf( '[BeepBeep AI] JWT token refresh failed: %s', $error_msg ) );
		}

		return new \WP_Error(
			'user_info_failed',
			$response['data']['error'] ?? __( 'Failed to get user info', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Sync identity with backend
	 */
	public function sync_identity() {
		// Get user email
		$email = $this->get_user_email();

		// If no email available and not authenticated, skip sync
		if ( empty( $email ) && ( ! $this->is_authenticated_callback || ! call_user_func( $this->is_authenticated_callback ) ) ) {
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log( 'info', 'Skipping identity sync - no email and not authenticated', array(), 'identity' );
			}
			return false;
		}

		// Get site URL
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

		// Get installation ID
		$site_id         = $this->get_site_id_callback ? call_user_func( $this->get_site_id_callback ) : '';
		$installation_id = ! empty( $site_id ) ? $site_id : null;

		// Get JWT token
		$jwt_token = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';

		// Build payload
		$payload = array(
			'email'  => $email,
			'plugin' => 'beepbeep-ai',
			'site'   => $site,
		);

		if ( ! empty( $installation_id ) ) {
			$payload['installationId'] = $installation_id;
		}

		if ( ! empty( $jwt_token ) ) {
			$payload['jwt'] = $jwt_token;
		}

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

		// Make request
		$response = $this->request_handler->make_request( '/identity/sync', 'POST', $payload, 30, false, array(), false );

		if ( is_wp_error( $response ) ) {
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

		// Check if response indicates failure
		if ( isset( $response['success'] ) && ! $response['success'] ) {
			$status_code   = $response['status_code'] ?? 0;
			$error_message = '';
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				$error_message = $response['data']['error'] ?? $response['data']['message'] ?? '';
			}

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
	 * Forgot password
	 */
	public function forgot_password( $email ) {
		// Temporarily clear token
		$temp_token = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';
		if ( $this->clear_token_callback ) {
			call_user_func( $this->clear_token_callback );
		}

		$site_url = admin_url( 'upload.php?page=bbai' );

		$response = $this->request_handler->make_request(
			'/auth/forgot-password',
			'POST',
			array(
				'email'   => $email,
				'siteUrl' => $site_url,
			),
			null,
			false,
			array(),
			false
		);

		// Restore token
		if ( $temp_token && $this->set_token_callback ) {
			call_user_func( $this->set_token_callback, $temp_token );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'] ?? array();
		}

		$error_message = $response['data']['error'] ?? $response['data']['message'] ?? __( 'Failed to send password reset email', 'beepbeep-ai-alt-text-generator' );

		if ( $response['status_code'] === 404 ) {
			$error_message = __( 'Password reset is currently being set up. This feature is not yet available on our backend. Please contact support for assistance.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $response['status_code'] === 429 ) {
			$error_message = __( 'Too many password reset requests. Please wait 15 minutes before trying again.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $response['status_code'] >= 500 ) {
			$error_message = __( 'The authentication server is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator' );
		}

		return new \WP_Error( 'forgot_password_failed', $error_message );
	}

	/**
	 * Reset password
	 */
	public function reset_password( $email, $token, $new_password ) {
		// Temporarily clear token
		$temp_token = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';
		if ( $this->clear_token_callback ) {
			call_user_func( $this->clear_token_callback );
		}

		$response = $this->request_handler->make_request(
			'/auth/reset-password',
			'POST',
			array(
				'email'       => $email,
				'token'       => $token,
				'newPassword' => $new_password,
				'password'    => $new_password,
			),
			null,
			false,
			array(),
			false
		);

		// Restore token
		if ( $temp_token && $this->set_token_callback ) {
			call_user_func( $this->set_token_callback, $temp_token );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'];
		}

		return new \WP_Error(
			'reset_password_failed',
			$response['data']['error'] ?? $response['data']['message'] ?? __( 'Failed to reset password', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Get user email for identity sync
	 */
	private function get_user_email() {
		$user_data = $this->get_user_data_callback ? call_user_func( $this->get_user_data_callback ) : null;
		if ( $user_data && isset( $user_data['email'] ) ) {
			return $user_data['email'];
		}

		// Fallback to WordPress user email
		$wp_user = wp_get_current_user();
		if ( $wp_user && $wp_user->ID > 0 ) {
			return $wp_user->user_email;
		}

		return '';
	}
}
