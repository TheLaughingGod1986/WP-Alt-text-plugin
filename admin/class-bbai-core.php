<?php
/**
 * Core implementation for the Alt Text AI plugin.
 *
 * This file contains the original plugin implementation and is loaded
 * by the WordPress Plugin Boilerplate friendly bootstrap.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// Constants should be defined in main plugin file, but provide fallbacks
if ( ! defined( 'BBAI_PLUGIN_FILE' ) ) {
	$plugin_file = defined( 'BEEPBEEP_AI_PLUGIN_FILE' ) && is_string( BEEPBEEP_AI_PLUGIN_FILE ) ? BEEPBEEP_AI_PLUGIN_FILE : dirname( __DIR__, 1 ) . '/beepbeep-ai-alt-text-generator.php';
	define( 'BBAI_PLUGIN_FILE', $plugin_file );
}

if ( ! defined( 'BBAI_PLUGIN_DIR' ) ) {
	define( 'BBAI_PLUGIN_DIR', defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ? BEEPBEEP_AI_PLUGIN_DIR : plugin_dir_path( BBAI_PLUGIN_FILE ) );
}

if ( ! defined( 'BBAI_PLUGIN_URL' ) ) {
	define( 'BBAI_PLUGIN_URL', defined( 'BEEPBEEP_AI_PLUGIN_URL' ) ? BEEPBEEP_AI_PLUGIN_URL : plugin_dir_url( BBAI_PLUGIN_FILE ) );
}

if ( ! defined( 'BBAI_PLUGIN_BASENAME' ) ) {
	$plugin_basename = defined( 'BEEPBEEP_AI_PLUGIN_BASENAME' ) && is_string( BEEPBEEP_AI_PLUGIN_BASENAME ) ? BEEPBEEP_AI_PLUGIN_BASENAME : '';
	if ( empty( $plugin_basename ) && defined( 'BBAI_PLUGIN_FILE' ) && is_string( BBAI_PLUGIN_FILE ) ) {
		$plugin_basename = plugin_basename( BBAI_PLUGIN_FILE );
	}
	if ( empty( $plugin_basename ) || ! is_string( $plugin_basename ) ) {
		$plugin_basename = 'beepbeep-ai-alt-text-generator.php';
	}
	define( 'BBAI_PLUGIN_BASENAME', $plugin_basename );
}

if ( ! defined( 'BBAI_VERSION' ) ) {
	define( 'BBAI_VERSION', defined( 'BEEPBEEP_AI_VERSION' ) ? BEEPBEEP_AI_VERSION : '4.2.3' );
}

// Load API clients, usage tracker, and queue infrastructure
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-debug-log.php';

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Debug_Log;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\API_Client_V2;

/**
 * Core implementation for the Alt Text AI plugin.
 *
 * Handles AJAX requests, REST API endpoints, usage tracking, and plugin administration.
 *
 * @package BeepBeepAI\AltTextGenerator
 * @since   4.2.3
 */
class Core {
	/**
	 * Option key for plugin settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'bbai_settings';

	/**
	 * Nonce key for security verification.
	 *
	 * @var string
	 */
	const NONCE_KEY  = 'bbai_nonce';

	/**
	 * Required capability for plugin management.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_bbbbai_text';

	/**
	 * Default Stripe checkout price IDs for different plans.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_CHECKOUT_PRICE_IDS = array(
		'pro'     => 'price_1SMrxaJl9Rm418cMM4iikjlJ',
		'agency'  => 'price_1SMrxaJl9Rm418cMnJTShXSY',
		'credits' => 'price_1SMrxbJl9Rm418cM0gkzZQZt',
	);

	/**
	 * Cached statistics data.
	 *
	 * @var array|null
	 */
	private $stats_cache = null;

	/**
	 * Token notice message.
	 *
	 * @var string|null
	 */
	private $token_notice = null;

	/**
	 * API client instance.
	 *
	 * @var API_Client_V2|null
	 */
	private $api_client = null;

	/**
	 * Cached checkout price IDs.
	 *
	 * @var array|null
	 */
	private $checkout_price_cache = null;

	/**
	 * Debug bootstrap data.
	 *
	 * @var array|null
	 */
	private $debug_bootstrap = null;

	/**
	 * Cached account summary data.
	 *
	 * @var array|null
	 */
	private $account_summary = null;

	/**
	 * Check if current user can manage the plugin.
	 *
	 * @since 4.2.3
	 * @return bool True if user has permission, false otherwise.
	 */
	public function user_can_manage() {
		// Check multiple capabilities to ensure access works.
		// Administrators should always have manage_options.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		// Also check the custom capability if it exists.
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}
		// Fallback: check if user is administrator role.
		$user = wp_get_current_user();
		if ( $user && in_array( 'administrator', (array) $user->roles ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Initialize the Core class.
	 *
	 * Sets up API client, migrates legacy options, and registers hooks.
	 *
	 * @since 4.2.3
	 */
	public function __construct() {
		// Ensure capability is set immediately in constructor (before any hooks).
		// This ensures administrators have the custom capability from the start.
		$this->ensure_capability();

		// Use Phase 2 API client (JWT-based authentication)
		$this->api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();
		// Soft-migrate legacy options to new prefixed keys
		$current = get_option( self::OPTION_KEY, null );
		if ( $current === null ) {
			foreach ( array( 'bbai_gpt_settings', 'beepbeepai_settings', 'opptibbai_settings', 'bbai_settings' ) as $legacy_key ) {
				$legacy_value = get_option( $legacy_key, null );
				if ( $legacy_value !== null ) {
					update_option( self::OPTION_KEY, $legacy_value, false );
					break;
				}
			}
		}

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			// Ensure table exists
			Debug_Log::create_table();

			// Log initialization
			Debug_Log::log(
				'info',
				'AI Alt Text plugin initialized',
				array(
					'version'       => BBAI_VERSION,
					'authenticated' => $this->api_client->is_authenticated() ? 'yes' : 'no',
				),
				'core'
			);

			update_option( 'bbai_logs_ready', true, false );
		}

		// Initialize credit usage logger hooks
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
		\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::init_hooks();

		// Check for migration on admin init
		add_action( 'admin_init', array( __CLASS__, 'maybe_run_migration' ), 5 );
	}

	/**
	 * Check and run migration if needed.
	 */
	public static function maybe_run_migration() {
		// Only run in admin and if not already migrated
		if ( ! is_admin() ) {
			return;
		}

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-migrate-usage.php';
		if ( ! \BeepBeepAI\AltTextGenerator\Migrate_Usage::is_migrated() ) {
			// Run migration in background (don't block admin page load)
			// Migration will run on first admin page load after activation
			if ( ! wp_next_scheduled( 'beepbeepai_run_migration' ) ) {
				wp_schedule_single_event( time() + 30, 'beepbeepai_run_migration' );
			}
		}
	}

	/**
	 * Run migration (called by cron).
	 */
	public static function run_migration() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-migrate-usage.php';
		\BeepBeepAI\AltTextGenerator\Migrate_Usage::migrate();
	}

	/**
	 * Expose API client for collaborators (REST controller, admin UI, etc.).
	 *
	 * @return API_Client_V2
	 */
	public function get_api_client() {
		return $this->api_client;
	}

	public function default_usage() {
		return array(
			'prompt'       => 0,
			'completion'   => 0,
			'total'        => 0,
			'requests'     => 0,
			'last_request' => null,
		);
	}

	/**
	 * Get post meta with backward compatibility for old ai_alt_ keys.
	 * Automatically migrates old keys to new beepbeepai_ keys.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key (without prefix).
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed Meta value.
	 */
	private function get_meta_with_compat( $post_id, $key, $single = true ) {
		$new_key = '_beepbeepai_' . $key;
		$old_key = '_ai_alt_' . $key;

		// Check for new key first
		$value = get_post_meta( $post_id, $new_key, $single );
		if ( $value !== '' && $value !== false && $value !== null ) {
			return $value;
		}

		// Check for old key and migrate if found
		$old_value = get_post_meta( $post_id, $old_key, $single );
		if ( $old_value !== '' && $old_value !== false && $old_value !== null ) {
			// Migrate to new key
			update_post_meta( $post_id, $new_key, $old_value );
			// Delete old key after migration
			delete_post_meta( $post_id, $old_key );
			return $old_value;
		}

		return $single ? '' : array();
	}

	/**
	 * Update post meta using new beepbeepai_ prefix.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key (without prefix).
	 * @param mixed  $value   Meta value.
	 * @return bool|int Result of update_post_meta.
	 */
	private function update_meta_with_compat( $post_id, $key, $value ) {
		$new_key = '_beepbeepai_' . $key;
		$old_key = '_ai_alt_' . $key;

		// Update new key
		$result = update_post_meta( $post_id, $new_key, $value );

		// Delete old key if it exists (migration cleanup)
		if ( metadata_exists( 'post', $post_id, $old_key ) ) {
			delete_post_meta( $post_id, $old_key );
		}

		return $result;
	}

	/**
	 * Delete post meta from both old and new keys.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key (without prefix).
	 * @return bool Result of delete_post_meta.
	 */
	private function delete_meta_with_compat( $post_id, $key ) {
		$new_key = '_beepbeepai_' . $key;
		$old_key = '_ai_alt_' . $key;

		$result1 = delete_post_meta( $post_id, $new_key );
		$result2 = delete_post_meta( $post_id, $old_key );

		return $result1 || $result2;
	}

	private function record_usage( $usage ) {
		$prompt     = isset( $usage['prompt'] ) ? max( 0, intval( $usage['prompt'] ) ) : 0;
		$completion = isset( $usage['completion'] ) ? max( 0, intval( $usage['completion'] ) ) : 0;
		$total      = isset( $usage['total'] ) ? max( 0, intval( $usage['total'] ) ) : ( $prompt + $completion );

		if ( ! $prompt && ! $completion && ! $total ) {
			return;
		}

		$opts                    = get_option( self::OPTION_KEY, array() );
		$current                 = $opts['usage'] ?? $this->default_usage();
		$current['prompt']      += $prompt;
		$current['completion']  += $completion;
		$current['total']       += $total;
		$current['requests']    += 1;
		$current['last_request'] = current_time( 'mysql' );

		$opts['usage']            = $current;
		$opts['token_alert_sent'] = $opts['token_alert_sent'] ?? false;
		$opts['token_limit']      = $opts['token_limit'] ?? 0;

		if ( ! empty( $opts['token_limit'] ) && ! $opts['token_alert_sent'] && $current['total'] >= $opts['token_limit'] ) {
			$opts['token_alert_sent'] = true;
			set_transient(
				'beepbeepai_token_notice',
				array(
					'total' => $current['total'],
					'limit' => $opts['token_limit'],
				),
				DAY_IN_SECONDS
			);
			$this->send_notification(
				__( 'AI Alt Text token usage alert', 'beepbeep-ai-alt-text-generator' ),
				sprintf(
					__( 'Cumulative token usage has reached %1$d (threshold %2$d). Consider reviewing your OpenAI usage.', 'beepbeep-ai-alt-text-generator' ),
					$current['total'],
					$opts['token_limit']
				)
			);
		}

		update_option( self::OPTION_KEY, $opts, false );
		$this->stats_cache = null;
	}

	/**
	 * Refresh usage snapshot from backend when a site license is active.
	 * Throttled to avoid hammering the API during bulk jobs.
	 */
	private function refresh_license_usage_snapshot( $force = false ) {
		if ( ! $this->api_client->has_active_license() ) {
			return;
		}

		$cache_key = 'bbai_usage_refresh_lock';
		if ( ! $force ) {
			$last_refresh = get_transient( $cache_key );
			if ( ! empty( $last_refresh ) ) {
				$elapsed = time() - intval( $last_refresh );
				if ( $elapsed < 60 ) {
					return;
				}
			}
		}

		$latest_usage = $this->api_client->get_usage();
		if ( is_wp_error( $latest_usage ) || ! is_array( $latest_usage ) ) {
			return;
		}

		Usage_Tracker::update_usage( $latest_usage );
		set_transient( $cache_key, time(), MINUTE_IN_SECONDS );
	}

	private function get_debug_bootstrap( $force_refresh = false ) {
		if ( $force_refresh || $this->debug_bootstrap === null ) {
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				$this->debug_bootstrap = Debug_Log::get_logs(
					array(
						'per_page' => 10,
						'page'     => 1,
					)
				);
			} else {
				$this->debug_bootstrap = array(
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
				);
			}
		}

		return $this->debug_bootstrap;
	}

	private function send_notification( $subject, $message ) {
		$opts  = get_option( self::OPTION_KEY, array() );
		$email = $opts['notify_email'] ?? get_option( 'admin_email' );
		$email = is_email( $email ) ? $email : get_option( 'admin_email' );
		if ( ! $email ) {
			return;
		}
		wp_mail( $email, $subject, $message );
	}

	public function ensure_capability() {
		// Ensure all administrator users have the custom capability.
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
			$role->add_cap( self::CAPABILITY );
		}

		// Also ensure super admins have it (for multisite).
		if ( is_multisite() ) {
			$super_admin_role = get_role( 'administrator' );
			if ( $super_admin_role && ! $super_admin_role->has_cap( self::CAPABILITY ) ) {
				$super_admin_role->add_cap( self::CAPABILITY );
			}
		}
	}

	public function maybe_display_threshold_notice() {
		if ( ! $this->user_can_manage() ) {
			return;
		}
		$data = get_transient( 'beepbeepai_token_notice' );
		if ( ! $data ) {
			// Fallback to legacy transient name during transition
			$data = get_transient( 'bbai_token_notice' );
		}
		if ( $data ) {
			$this->token_notice = $data;
			add_action( 'admin_notices', array( $this, 'render_token_notice' ) );
		}
	}

	/**
	 * Allow direct checkout links to create Stripe sessions without JavaScript
	 */
	public function maybe_handle_direct_checkout() {
		if ( ! is_admin() ) {
			return; }
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( $page !== 'bbai-checkout' ) {
			return; }

		if ( ! $this->user_can_manage() ) {
			wp_die( __( 'You do not have permission to perform this action.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$nonce_raw = isset( $_GET['_bbai_nonce'] ) && $_GET['_bbai_nonce'] !== null ? wp_unslash( $_GET['_bbai_nonce'] ) : '';
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bbai_direct_checkout' ) ) {
			wp_die( __( 'Security check failed. Please try again from the dashboard.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$plan_raw     = isset( $_GET['plan'] ) ? wp_unslash( $_GET['plan'] ) : ( isset( $_GET['type'] ) ? wp_unslash( $_GET['type'] ) : '' );
		$plan_param   = sanitize_key( $plan_raw );
		$price_id_raw = isset( $_GET['price_id'] ) && $_GET['price_id'] !== null ? wp_unslash( $_GET['price_id'] ) : '';
		$price_id     = is_string( $price_id_raw ) ? sanitize_text_field( $price_id_raw ) : '';
		$fallback     = Usage_Tracker::get_upgrade_url();

		if ( $plan_param ) {
			$mapped_price = $this->get_checkout_price_id( $plan_param );
			if ( ! empty( $mapped_price ) ) {
				$price_id = $mapped_price;
			}
		}

		if ( empty( $price_id ) ) {
			wp_safe_redirect( $fallback );
			exit;
		}

		$success_url = admin_url( 'upload.php?page=bbai&checkout=success' );
		$cancel_url  = admin_url( 'upload.php?page=bbai&checkout=cancel' );

		$result = $this->api_client->create_checkout_session( $price_id, $success_url, $cancel_url );

		if ( is_wp_error( $result ) || empty( $result['url'] ) ) {
			$message    = is_wp_error( $result ) ? $result->get_error_message() : __( 'Unable to start checkout. Please try again.', 'beepbeep-ai-alt-text-generator' );
			$plan_raw   = isset( $_GET['plan'] ) ? wp_unslash( $_GET['plan'] ) : ( isset( $_GET['type'] ) ? wp_unslash( $_GET['type'] ) : '' );
			$plan_param = sanitize_key( $plan_raw );
			$query_args = array(
				'page'           => 'beepbeep-ai-alt-text-generator',
				'checkout_error' => rawurlencode( $message ),
			);
			if ( ! empty( $plan_param ) ) {
				$query_args['plan'] = $plan_param;
			}
			$redirect = add_query_arg( $query_args, admin_url( 'upload.php' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		// Redirect to Stripe checkout
		wp_safe_redirect( $result['url'] );
		exit;
		exit;
	}

	/**
	 * Retrieve checkout price IDs sourced from the backend
	 */
	public function get_checkout_price_ids() {
		if ( is_array( $this->checkout_price_cache ) ) {
			return $this->checkout_price_cache;
		}

		$prices = self::DEFAULT_CHECKOUT_PRICE_IDS;

		$cached = get_transient( 'bbai_remote_price_ids' );
		if ( ! is_array( $cached ) ) {
			$plans = $this->api_client->get_plans();
			if ( ! is_wp_error( $plans ) && ! empty( $plans ) ) {
				$remote = array();
				foreach ( $plans as $plan ) {
					if ( ! is_array( $plan ) ) {
						continue;
					}
					$plan_id  = isset( $plan['id'] ) && is_string( $plan['id'] ) ? sanitize_key( $plan['id'] ) : '';
					$price_id = ! empty( $plan['priceId'] ) && is_string( $plan['priceId'] ) ? sanitize_text_field( $plan['priceId'] ) : '';
					if ( $plan_id && $price_id ) {
						$remote[ $plan_id ] = $price_id;
					}
				}
				if ( ! empty( $remote ) ) {
					set_transient( 'bbai_remote_price_ids', $remote, 10 * MINUTE_IN_SECONDS );
					$cached = $remote;
				}
			}
		}

		if ( is_array( $cached ) ) {
			foreach ( $cached as $plan_id => $price_id ) {
				$plan_id  = is_string( $plan_id ) ? sanitize_key( $plan_id ) : '';
				$price_id = is_string( $price_id ) ? sanitize_text_field( $price_id ) : '';
				if ( $plan_id && $price_id ) {
					$prices[ $plan_id ] = $price_id;
				}
			}
		}

		// Backwards compatibility: use saved overrides when a plan is missing a mapped price.
		$stored = get_option( 'bbai_checkout_prices', array() );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			foreach ( $stored as $key => $value ) {
				$key   = is_string( $key ) ? sanitize_key( $key ) : '';
				$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
				if ( $key && $value && empty( $prices[ $key ] ) ) {
					$prices[ $key ] = $value;
				}
			}
		}

		$prices                     = apply_filters( 'bbai_checkout_price_ids', $prices );
		$this->checkout_price_cache = $prices;
		return $prices;
	}

	/**
	 * Helper to grab a single price ID
	 */
	public function get_checkout_price_id( $plan ) {
		$prices   = $this->get_checkout_price_ids();
		$plan     = is_string( $plan ) ? sanitize_key( $plan ) : '';
		$price_id = $prices[ $plan ] ?? '';
		return apply_filters( 'bbai_checkout_price_id', $price_id, $plan, $prices );
	}

	/**
	 * Surface checkout success/error notices in WP Admin
	 */
	public function maybe_render_checkout_notices() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( $page !== 'beepbeep-ai-alt-text-generator' ) {
			return;
		}

		$checkout_error = isset( $_GET['checkout_error'] ) ? wp_unslash( $_GET['checkout_error'] ) : '';
		if ( ! empty( $checkout_error ) ) {
			$message  = is_string( $checkout_error ) ? sanitize_text_field( $checkout_error ) : '';
			$plan_raw = isset( $_GET['plan'] ) ? wp_unslash( $_GET['plan'] ) : '';
			$plan     = is_string( $plan_raw ) ? sanitize_text_field( $plan_raw ) : '';
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Unable to start checkout', 'beepbeep-ai-alt-text-generator' ); ?>:</strong>
					<?php echo esc_html( $message ); ?>
					<?php if ( $plan ) : ?>
						(<?php echo esc_html( sprintf( __( 'Plan: %s', 'beepbeep-ai-alt-text-generator' ), $plan ) ); ?>)
					<?php endif; ?>
				</p>
				<p><?php esc_html_e( 'Please check your account connection and try again. If the problem persists, contact support.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
			<?php
		} else {
			$checkout = isset( $_GET['checkout'] ) ? sanitize_key( wp_unslash( $_GET['checkout'] ) ) : '';
			if ( $checkout === 'success' ) {
				$site_hash = '';
				if ( method_exists( $this->api_client, 'get_site_id' ) ) {
					$site_hash = sanitize_text_field( $this->api_client->get_site_id() );
				}
				$rest_nonce    = wp_create_nonce( 'wp_rest' );
				$rest_endpoint = esc_url_raw( rest_url( 'bbai/v1/license/attach' ) );
				$site_url      = get_site_url();
				?>
				<div class="notice notice-info bbai-auto-attach-notice" data-site-url="<?php echo esc_attr( $site_url ); ?>" data-site-hash="<?php echo esc_attr( $site_hash ); ?>" data-install-id="<?php echo esc_attr( $site_hash ); ?>" data-status-pending="<?php esc_attr_e( 'Syncing your new license...', 'beepbeep-ai-alt-text-generator' ); ?>" data-status-success="<?php esc_attr_e( 'License synced! Refreshing your dashboard...', 'beepbeep-ai-alt-text-generator' ); ?>" data-status-error="<?php esc_attr_e( 'Automatic activation failed. Please use the license key from your email or contact support.', 'beepbeep-ai-alt-text-generator' ); ?>">
					<p><strong><?php esc_html_e( 'Activating your OptiAI license...', 'beepbeep-ai-alt-text-generator' ); ?></strong></p>
					<p><?php esc_html_e( 'We found your new plan and are applying it to this site automatically. This usually takes less than a minute.', 'beepbeep-ai-alt-text-generator' ); ?></p>
					<p class="bbai-auto-attach-status" aria-live="polite"></p>
				</div>
				<script>
				(function() {
					const restNonce = '<?php echo esc_js( $rest_nonce ); ?>';
					const restEndpoint = '<?php echo esc_js( $rest_endpoint ); ?>';
					window.addEventListener('DOMContentLoaded', function bbaiAutoAttachInit() {
						const notice = document.querySelector('.bbai-auto-attach-notice');
						if (!notice || notice.dataset.autoAttachInit === '1') {
							return;
						}
						notice.dataset.autoAttachInit = '1';
						const statusNode = notice.querySelector('.bbai-auto-attach-status');
						if (statusNode) {
							statusNode.textContent = notice.dataset.statusPending || 'Syncing your new license...';
						}
						const payload = {
							siteUrl: notice.dataset.siteUrl || window.location.origin,
							siteHash: notice.dataset.siteHash || '',
							installId: notice.dataset.installId || notice.dataset.siteHash || ''
						};
						fetch(restEndpoint, {
							method: 'POST',
							credentials: 'same-origin',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': restNonce
							},
							body: JSON.stringify(payload)
						})
						.then(function(response) {
							if (!response.ok) {
								throw new Error('HTTP ' + response.status);
							}
							return response.json();
						})
						.then(function(data) {
							if (!data || data.success !== true) {
								throw new Error(data && data.message ? data.message : 'Unknown error');
							}
							notice.classList.remove('notice-info');
							notice.classList.add('notice-success', 'is-dismissible');
							if (statusNode) {
								statusNode.textContent = data.message || notice.dataset.statusSuccess || 'License synced! Refreshing your dashboard...';
							}
							if (typeof window.alttextai_refresh_usage === 'function') {
								window.alttextai_refresh_usage();
							}
							if (window.history && history.replaceState) {
								const params = new URLSearchParams(window.location.search);
								params.delete('checkout');
								const newQuery = params.toString();
								const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '') + window.location.hash;
								history.replaceState({}, document.title, newUrl);
							}
						})
						.catch(function(error) {
							console.error('[OptiAI] Auto-attach failed', error);
							notice.classList.remove('notice-info');
							notice.classList.add('notice-error');
							if (statusNode) {
								statusNode.textContent = notice.dataset.statusError || error.message || 'Automatic activation failed. Please use the license key from your email or contact support.';
							}
						});
					});
				})();
				</script>
				<?php
			} elseif ( $checkout === 'cancel' ) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p><?php esc_html_e( 'Checkout cancelled. Your plan remains unchanged. Upgrade anytime to unlock 1,000 generations per month with Pro.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				</div>
				<?php
			}
		}

		// Password reset notices
		$password_reset = isset( $_GET['password_reset'] ) ? sanitize_key( wp_unslash( $_GET['password_reset'] ) ) : '';
		if ( $password_reset === 'requested' ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Password Reset Email Sent', 'beepbeep-ai-alt-text-generator' ); ?></strong></p>
				<p><?php esc_html_e( 'Check your email inbox (and spam folder) for password reset instructions. The link will expire in 1 hour.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
			<?php
		} elseif ( $password_reset === 'success' ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Password Reset Successful', 'beepbeep-ai-alt-text-generator' ); ?></strong></p>
				<p><?php esc_html_e( 'Your password has been updated. You can now sign in with your new password.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
			<?php
		}

		// Subscription update notices
		$subscription_updated = isset( $_GET['subscription_updated'] ) ? sanitize_key( wp_unslash( $_GET['subscription_updated'] ) ) : '';
		if ( ! empty( $subscription_updated ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Subscription Updated', 'beepbeep-ai-alt-text-generator' ); ?></strong></p>
				<p><?php esc_html_e( 'Your subscription information has been refreshed.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
			<?php
		}

		$portal_return = isset( $_GET['portal_return'] ) ? sanitize_key( wp_unslash( $_GET['portal_return'] ) ) : '';
		if ( $portal_return === 'success' ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Billing Updated', 'beepbeep-ai-alt-text-generator' ); ?></strong></p>
				<p><?php esc_html_e( 'Your billing information has been updated successfully. Changes may take a few moments to reflect.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
			<?php
		}
	}

	public function render_token_notice() {
		if ( empty( $this->token_notice ) ) {
			return;
		}
		delete_transient( 'beepbeepai_token_notice' );
		delete_transient( 'bbai_token_notice' );
		$total = number_format_i18n( $this->token_notice['total'] ?? 0 );
		$limit = number_format_i18n( $this->token_notice['limit'] ?? 0 );
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( sprintf( __( 'BeepBeep AI – Alt Text Generator has used %1$s tokens (threshold %2$s). Consider reviewing usage.', 'beepbeep-ai-alt-text-generator' ), $total, $limit ) ) . '</p></div>';
		$this->token_notice = null;
	}

	public function maybe_render_queue_notice() {
		if ( ! isset( $_GET['bbai_queued'] ) ) {
			return;
		}
		$count_raw = isset( $_GET['bbai_queued'] ) ? wp_unslash( $_GET['bbai_queued'] ) : '';
		$count     = absint( $count_raw );
		if ( $count <= 0 ) {
			return;
		}
		$message = $count === 1
			? __( '1 image queued for background optimisation. The alt text will appear shortly.', 'beepbeep-ai-alt-text-generator' )
			: sprintf( __( 'Queued %d images for background optimisation. Alt text will be generated shortly.', 'beepbeep-ai-alt-text-generator' ), $count );
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Display external API compliance modal (WordPress.org requirement).
	 * Shows once as a popup after activation to inform users about external service usage.
	 * Rendered in admin_footer so it appears as a modal overlay.
	 */
	public function maybe_render_external_api_notice() {
		// Only show on plugin admin pages
		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) || ! is_string( $screen->id ) ) {
			return;
		}
		$screen_id = (string) $screen->id;
		if ( strpos( $screen_id, 'bbai' ) === false && strpos( $screen_id, 'ai-alt' ) === false ) {
			return;
		}

		// Check if modal has been dismissed (site-wide option, shows once for all users)
		$dismissed = get_option( 'wp_alt_text_api_notice_dismissed', false );
		if ( $dismissed ) {
			return;
		}

		// Show modal popup if not dismissed
		$api_url     = 'https://alttext-ai-backend.onrender.com';
		$privacy_url = 'https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/';
		$terms_url   = 'https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/';
		$nonce       = wp_create_nonce( 'beepbeepai_nonce' );
		?>
		<div id="bbai-api-notice-modal" class="bbai-modal-backdrop" style="display: none; opacity: 0;" role="dialog" aria-modal="true" aria-labelledby="bbai-api-notice-title" aria-describedby="bbai-api-notice-desc">
			<div class="bbai-upgrade-modal__content bbai-api-notice-modal-content" style="max-width: 600px;">
				<div class="bbai-upgrade-modal__header">
					<div class="bbai-upgrade-modal__header-content">
						<h2 id="wp-alt-text-api-notice-title"><?php esc_html_e( 'External Service Notice', 'beepbeep-ai-alt-text-generator' ); ?></h2>
					</div>
					<button type="button" class="bbai-modal-close" onclick="bbaiCloseApiNotice();" aria-label="<?php esc_attr_e( 'Close notice', 'beepbeep-ai-alt-text-generator' ); ?>">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
							<path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
				
				<div class="bbai-upgrade-modal__body" id="bbai-api-notice-desc" style="padding: 24px;">
					<p style="margin: 0 0 20px 0; color: #374151; line-height: 1.6; font-size: 14px;">
						<?php esc_html_e( 'This plugin connects to an external API service to generate alt text. Image data is transmitted securely to process generation. No personal user data is collected.', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
					
					<div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; margin-bottom: 0;">
						<p style="margin: 0 0 12px 0; font-weight: 600; color: #111827; font-size: 14px;">
							<?php esc_html_e( 'API Endpoint:', 'beepbeep-ai-alt-text-generator' ); ?>
						</p>
						<p style="margin: 0 0 16px 0; color: #6B7280; font-size: 13px; font-family: monospace; word-break: break-all; line-height: 1.5;">
							<?php echo esc_html( $api_url ); ?>
						</p>
						
						<p style="margin: 0 0 8px 0; font-weight: 600; color: #111827; font-size: 14px;">
							<?php esc_html_e( 'Privacy Policy:', 'beepbeep-ai-alt-text-generator' ); ?>
						</p>
						<p style="margin: 0 0 16px 0;">
							<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener" style="color: #2563EB; text-decoration: underline; font-size: 13px;">
								<?php echo esc_html( $privacy_url ); ?>
							</a>
						</p>
						
						<p style="margin: 0 0 8px 0; font-weight: 600; color: #111827; font-size: 14px;">
							<?php esc_html_e( 'Terms of Service:', 'beepbeep-ai-alt-text-generator' ); ?>
						</p>
						<p style="margin: 0;">
							<a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener" style="color: #2563EB; text-decoration: underline; font-size: 13px;">
								<?php echo esc_html( $terms_url ); ?>
							</a>
						</p>
					</div>
				</div>
				
				<div class="bbai-upgrade-modal__footer" style="padding: 20px 24px; border-top: 1px solid #E5E7EB; text-align: right; background: #FFFFFF;">
					<button type="button" class="button button-primary" onclick="bbaiCloseApiNotice();" style="min-width: 100px;">
						<?php esc_html_e( 'Got it', 'beepbeep-ai-alt-text-generator' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<script>
		(function($) {
			// Ensure jQuery and ajaxurl are available
			if (typeof $ === 'undefined') {
				console.error('[WP Alt Text AI] jQuery is required for the API notice modal');
				return;
			}
			
			// Show modal on page load with a small delay to ensure styles are loaded
			$(document).ready(function() {
				var $modal = $('#bbai-api-notice-modal');
				if ($modal.length === 0) {
					return;
				}
				
				// Small delay to ensure CSS is loaded
				setTimeout(function() {
					$modal.css({
						'display': 'flex',
						'opacity': '0'
					}).animate({
						'opacity': '1'
					}, 300);
				}, 500);
			});
			
			// Handle close button
			window.bbaiCloseApiNotice = function() {
				var $modal = $('#bbai-api-notice-modal');
				if ($modal.length === 0) {
					return;
				}
				
				// Fade out and remove
				$modal.animate({
					'opacity': '0'
				}, 200, function() {
					// Dismiss via AJAX
					var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: 'beepbeepai_dismiss_api_notice',
							nonce: '<?php echo esc_js( $nonce ); ?>'
						},
						success: function(response) {
							console.log('[WP Alt Text AI] API notice dismissed');
						},
						error: function() {
							console.log('[WP Alt Text AI] Failed to dismiss notice');
						}
					});
					
					// Remove modal from DOM
					$modal.remove();
				});
			};
			
			// Close on backdrop click (use event delegation)
			$(document).on('click', '#bbai-api-notice-modal.bbai-modal-backdrop', function(e) {
				if (e.target === this) {
					e.preventDefault();
					e.stopPropagation();
					bbaiCloseApiNotice();
				}
			});
			
			// Close on ESC key
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' || e.keyCode === 27) {
					var $modal = $('#bbai-api-notice-modal');
					if ($modal.length > 0 && $modal.is(':visible')) {
						bbaiCloseApiNotice();
					}
				}
			});
		})(jQuery);
		</script>
		<?php
	}

	public function deactivate() {
		wp_clear_scheduled_hook( Queue::CRON_HOOK );
	}

	public function activate() {
		global $wpdb;

		Queue::create_table();
		Queue::schedule_processing( 10 );
		Debug_Log::create_table();
		update_option( 'bbai_logs_ready', true, false );

		// Create credit usage table
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
		\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::create_table();

		// Create usage logs table for multi-user visualization
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
		\BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::create_table();

		// Migrate existing usage logs table to include site_id if needed
		$this->migrate_usage_logs_table();

		// Generate site fingerprint (one-time per site)
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
		\BeepBeepAI\AltTextGenerator\Site_Fingerprint::generate();

		// Ensure site identifier exists
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		\BeepBeepAI\AltTextGenerator\get_site_identifier();

		// Create database indexes for performance
		$this->create_performance_indexes();

		$defaults = array(
			'api_url'          => 'https://alttext-ai-backend.onrender.com',
			'model'            => 'gpt-4o-mini',
			'max_words'        => 16,
			'language'         => 'en-GB',
			'language_custom'  => '',
			'enable_on_upload' => true,
			'tone'             => 'professional, accessible',
			'force_overwrite'  => false,
			'token_limit'      => 0,
			'token_alert_sent' => false,
			'dry_run'          => false,
			'custom_prompt'    => '',
			'notify_email'     => get_option( 'admin_email' ),
			'usage'            => $this->default_usage(),
		);
		$existing = get_option( self::OPTION_KEY, array() );
		$updated  = wp_parse_args( $existing, $defaults );

		// ALWAYS force production API URL
		$updated['api_url'] = 'https://alttext-ai-backend.onrender.com';

		update_option( self::OPTION_KEY, $updated, false );

		// Clear any invalid cached tokens
		delete_option( 'bbai_jwt_token' );
		delete_option( 'bbai_user_data' );
		delete_transient( 'bbai_token_last_check' );

		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
			$role->add_cap( self::CAPABILITY );
		}
	}

	private function create_performance_indexes() {
		global $wpdb;

		// Index for _bbai_generated_at (used in sorting and stats)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
		$wpdb->query(
			"
            CREATE INDEX idx_bbai_generated_at 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(50))
        "
		);

		// Index for _bbai_source (used in stats aggregation)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
		$wpdb->query(
			"
            CREATE INDEX idx_bbai_source 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(50))
        "
		);

		// Index for _wp_attachment_image_alt (used in coverage stats)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
		$wpdb->query(
			"
            CREATE INDEX idx_wp_attachment_alt 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(100))
        "
		);

		// Composite index for attachment queries
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
		$wpdb->query(
			"
            CREATE INDEX idx_posts_attachment_image 
            ON {$wpdb->posts} (post_type(20), post_mime_type(20), post_status(20))
        "
		);
	}

	/**
	 * Migrate usage logs table to include site_id column (backwards compatibility)
	 */
	private function migrate_usage_logs_table() {
		global $wpdb;
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';

		$table_name = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();

		// Check if site_id column exists
		// Table name cannot be used as placeholder - must escape it
		$table_name_escaped = esc_sql( $table_name );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped with esc_sql
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s",
				'site_id'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add site_id column
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
			$site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"
                ALTER TABLE `{$table_name}`
                ADD COLUMN site_id VARCHAR(64) NOT NULL DEFAULT '' AFTER user_id,
                ADD KEY site_id (site_id),
                ADD KEY site_created (site_id, created_at)
            "
			);

			// Update existing rows with current site_id
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table_name}` SET site_id = %s WHERE site_id = ''",
					$site_id
				)
			);
		}
	}

	public function add_settings_page() {
		// Ensure capability is set before registering menu.
		$this->ensure_capability();

		// Use 'upload_files' as the capability - this is required by add_media_page().
		// Administrators always have this capability.
		// Fine-grained permission checks happen inside render_settings_page() via user_can_manage().
		$hook = add_media_page(
			'BeepBeep AI – Alt Text Generator',
			'BeepBeep AI',
			'upload_files', // Required capability for media pages
			'bbai',
			array( $this, 'render_settings_page' )
		);

		// If menu registration failed, log it for debugging.
		if ( empty( $hook ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BeepBeep AI: Failed to register admin menu. Current user can upload_files: ' . ( current_user_can( 'upload_files' ) ? 'yes' : 'no' ) );
		}

		// Hidden checkout redirect page
		add_submenu_page(
			null, // No parent = hidden from menu
			'Checkout',
			'Checkout',
			'upload_files', // Use same capability as main menu
			'bbai-checkout',
			array( $this, 'handle_checkout_redirect' )
		);
	}

	public function handle_checkout_redirect() {
		if ( ! $this->api_client->is_authenticated() ) {
			wp_die( 'Please sign in first to upgrade.' );
		}

		$price_id_raw = isset( $_GET['price_id'] ) ? wp_unslash( $_GET['price_id'] ) : '';
		$price_id     = is_string( $price_id_raw ) ? sanitize_text_field( $price_id_raw ) : '';
		if ( empty( $price_id ) ) {
			wp_die( 'Invalid checkout request.' );
		}

		$success_url = admin_url( 'upload.php?page=bbai&checkout=success' );
		$cancel_url  = admin_url( 'upload.php?page=bbai&checkout=cancel' );

		$result = $this->api_client->create_checkout_session( $price_id, $success_url, $cancel_url );

		if ( is_wp_error( $result ) ) {
			wp_die( 'Checkout error: ' . $result->get_error_message() );
		}

		if ( ! empty( $result['url'] ) ) {
			wp_safe_redirect( $result['url'] );
			exit;
		}

		wp_die( 'Failed to create checkout session.' );
	}

	public function register_settings() {
		register_setting(
			'bbai_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $input ) {
					$existing = get_option( self::OPTION_KEY, array() );
					$input    = is_array( $input ) ? $input : array();
					$out      = array();
					// ALWAYS force production API URL - no user input allowed
					$production_url   = 'https://alttext-ai-backend.onrender.com';
					$out['api_url']   = $production_url;
					$model            = isset( $input['model'] ) ? (string) $input['model'] : 'gpt-4o-mini';
					$out['model']     = $model ? sanitize_text_field( $model ) : 'gpt-4o-mini';
					$out['max_words'] = max( 4, intval( $input['max_words'] ?? 16 ) );
					$lang_input_raw   = isset( $input['language'] ) ? (string) $input['language'] : 'en-GB';
					$lang_input       = $lang_input_raw ? sanitize_text_field( $lang_input_raw ) : 'en-GB';
					$custom_input_raw = isset( $input['language_custom'] ) ? (string) $input['language_custom'] : '';
					$custom_input     = $custom_input_raw ? sanitize_text_field( $custom_input_raw ) : '';
					if ( $lang_input === 'custom' ) {
						$out['language']        = $custom_input ?: 'en-GB';
						$out['language_custom'] = $custom_input;
					} else {
						$out['language']        = $lang_input ?: 'en-GB';
						$out['language_custom'] = '';
					}
					$out['enable_on_upload'] = ! empty( $input['enable_on_upload'] );
					$tone                    = isset( $input['tone'] ) ? (string) $input['tone'] : 'professional, accessible';
					$out['tone']             = $tone ? sanitize_text_field( $tone ) : 'professional, accessible';
					$out['force_overwrite']  = ! empty( $input['force_overwrite'] );
					$out['token_limit']      = max( 0, intval( $input['token_limit'] ?? 0 ) );
					if ( $out['token_limit'] === 0 ) {
						$out['token_alert_sent'] = false;
					} elseif ( intval( $existing['token_limit'] ?? 0 ) !== $out['token_limit'] ) {
						$out['token_alert_sent'] = false;
					} else {
						$out['token_alert_sent'] = ! empty( $existing['token_alert_sent'] );
					}
					$out['dry_run']       = ! empty( $input['dry_run'] );
					$custom_prompt        = isset( $input['custom_prompt'] ) ? (string) $input['custom_prompt'] : '';
					$out['custom_prompt'] = $custom_prompt ? wp_kses_post( $custom_prompt ) : '';
					$notify_raw           = $input['notify_email'] ?? ( $existing['notify_email'] ?? get_option( 'admin_email' ) );
					$notify               = is_string( $notify_raw ) ? sanitize_text_field( $notify_raw ) : '';
					$out['notify_email']  = $notify && is_email( $notify ) ? $notify : ( $existing['notify_email'] ?? get_option( 'admin_email' ) );
					$out['usage']         = $existing['usage'] ?? $this->default_usage();

					return $out;
				},
			)
		);
	}

	public function render_settings_page() {
		// Double-check permissions - if user can upload files, they should be able to access this.
		// This is a media page, so upload_files is the primary requirement.
		if ( ! current_user_can( 'upload_files' ) && ! $this->user_can_manage() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'beepbeep-ai-alt-text-generator' ) );
		}
		$opts  = get_option( self::OPTION_KEY, array() );
		$stats = $this->get_media_stats();
		$nonce = wp_create_nonce( self::NONCE_KEY );

		// Check if there's a registered user (authenticated or has active license)
		$is_authenticated    = $this->api_client->is_authenticated();
		$has_license         = $this->api_client->has_active_license();
		$has_registered_user = $is_authenticated || $has_license;

		// Build tabs - show only Dashboard and How to if no registered user
		if ( ! $has_registered_user ) {
			$tabs = array(
				'dashboard' => __( 'Dashboard', 'beepbeep-ai-alt-text-generator' ),
				'guide'     => __( 'How to', 'beepbeep-ai-alt-text-generator' ),
			);

			// Force dashboard tab if trying to access restricted tabs
			$allowed_tabs = array( 'dashboard', 'guide' );
			$tab          = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
			if ( ! in_array( $tab, $allowed_tabs ) ) {
				$tab = 'dashboard';
			}

			// Not a registered user - set defaults
			$is_pro_for_admin    = false;
			$is_agency_for_admin = false;
		} else {
			// Determine if agency license
			$has_license  = $this->api_client->has_active_license();
			$license_data = $this->api_client->get_license_data();
			$plan_slug    = isset( $usage_stats ) && isset( $usage_stats['plan'] ) ? $usage_stats['plan'] : 'free';

			// If using license, check license plan
			if ( $has_license && $license_data && isset( $license_data['organization'] ) ) {
				$license_plan = strtolower( $license_data['organization']['plan'] ?? 'free' );
				if ( $license_plan !== 'free' ) {
					$plan_slug = $license_plan;
				}
			}

			$is_agency = ( $plan_slug === 'agency' );
			$is_pro    = ( $plan_slug === 'pro' || $plan_slug === 'agency' );

			// Show all tabs for registered users
			$tabs = array(
				'dashboard'    => __( 'Dashboard', 'beepbeep-ai-alt-text-generator' ),
				'library'      => __( 'ALT Library', 'beepbeep-ai-alt-text-generator' ),
				'credit-usage' => __( 'Credit Usage', 'beepbeep-ai-alt-text-generator' ),
				'guide'        => __( 'How to', 'beepbeep-ai-alt-text-generator' ),
			);

			// For agency and pro: add Admin tab (contains Debug Logs and Settings)
			// For non-premium authenticated users: show Debug Logs and Settings tabs
			if ( $is_pro ) {
				$tabs['admin'] = __( 'Admin', 'beepbeep-ai-alt-text-generator' );
			} elseif ( $is_authenticated ) {
				$tabs['debug']    = __( 'Debug Logs', 'beepbeep-ai-alt-text-generator' );
				$tabs['settings'] = __( 'Settings', 'beepbeep-ai-alt-text-generator' );
			}

			$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

			// If trying to access restricted tabs, redirect to dashboard
			if ( ! in_array( $tab, array_keys( $tabs ) ) ) {
				$tab = 'dashboard';
			}

			// Set variables for Admin tab access (used later in template)
			$is_pro_for_admin    = $is_pro;
			$is_agency_for_admin = $is_agency;
		}
		$export_url      = wp_nonce_url( admin_url( 'admin-post.php?action=bbai_usage_export' ), 'bbai_usage_export' );
		$audit_rows      = $stats['audit'] ?? array();
		$debug_bootstrap = $this->get_debug_bootstrap();
		?>
		<div class="wrap bbai-wrap bbai-modern">
			<!-- Dark Header -->
			<div class="bbai-header">
				<div class="bbai-header-content">
					<div class="bbai-logo">
						<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon">
							<rect width="40" height="40" rx="10" fill="url(#logo-gradient)"/>
							<!-- AI/Sparkle icon representing intelligence -->
							<circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
							<path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
							<path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
							<path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
							<!-- Image frame representing image optimization -->
							<rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
							<defs>
								<linearGradient id="logo-gradient" x1="0" y1="0" x2="40" y2="40">
									<stop stop-color="#14b8a6"/>
									<stop offset="1" stop-color="#10b981"/>
								</linearGradient>
							</defs>
						</svg>
						<div class="bbai-logo-content">
							<span class="bbai-logo-text"><?php esc_html_e( 'BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator' ); ?></span>
							<span class="bbai-logo-tagline"><?php esc_html_e( 'WordPress AI Tools', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
					</div>
					<nav class="bbai-nav">
						<?php
						foreach ( $tabs as $slug => $label ) :
							$url    = esc_url( add_query_arg( array( 'tab' => $slug ) ) );
							$active = $tab === $slug ? ' active' : '';
							?>
							<a href="<?php echo esc_url( $url ); ?>" class="bbai-nav-link<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
					<!-- Auth & Subscription Actions -->
					<div class="bbai-header-actions">
						<?php
						$has_license      = $this->api_client->has_active_license();
						$is_authenticated = $this->api_client->is_authenticated();

						if ( $is_authenticated || $has_license ) :
							$usage_stats     = Usage_Tracker::get_stats_display();
							$account_summary = $is_authenticated ? $this->get_account_summary( $usage_stats ) : null;
							$plan_slug       = $usage_stats['plan'] ?? 'free';
							$plan_label      = isset( $usage_stats['plan_label'] ) ? (string) $usage_stats['plan_label'] : ucfirst( $plan_slug );
							$connected_email = isset( $account_summary['email'] ) ? (string) $account_summary['email'] : '';
							$billing_portal  = Usage_Tracker::get_billing_portal_url();

							// If license-only mode (no personal login), show license info
							if ( $has_license && ! $is_authenticated ) {
								$license_data    = $this->api_client->get_license_data();
								$org_name        = isset( $license_data['organization']['name'] ) ? (string) $license_data['organization']['name'] : '';
								$connected_email = $org_name ?: __( 'License Active', 'beepbeep-ai-alt-text-generator' );
							}
							?>
							<!-- Compact Account Bar in Header -->
							<div class="bbai-header-account-bar">
								<span class="bbai-header-account-email"><?php echo esc_html( is_string( $connected_email ) ? $connected_email : __( 'Connected', 'beepbeep-ai-alt-text-generator' ) ); ?></span>
								<span class="bbai-header-plan-badge"><?php echo esc_html( is_string( $plan_label ) ? $plan_label : ucfirst( $plan_slug ?? 'free' ) ); ?></span>
								<?php if ( $plan_slug === 'free' && ! $has_license ) : ?>
									<button type="button" class="bbai-header-upgrade-btn" data-action="show-upgrade-modal">
										<?php esc_html_e( 'Upgrade to Pro — 1,000 Generations Monthly', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php elseif ( ! empty( $billing_portal ) && $is_authenticated ) : ?>
									<button type="button" class="bbai-header-manage-btn" data-action="open-billing-portal">
										<?php esc_html_e( 'Manage', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php endif; ?>
								<?php if ( $is_authenticated ) : ?>
								<button type="button" class="bbai-header-disconnect-btn" data-action="disconnect-account">
									<?php esc_html_e( 'Disconnect', 'beepbeep-ai-alt-text-generator' ); ?>
								</button>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<button type="button" class="bbai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M10 14H13C13.5523 14 14 13.5523 14 13V3C14 2.44772 13.5523 2 13 2H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
									<path d="M5 11L2 8L5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M2 8H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
								</svg>
								<span><?php esc_html_e( 'Login', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</button>
						<?php endif; ?>
				</div>
				</div>
			</div>
			
			<!-- Main Content Container -->
			<div class="bbai-container">

			<?php if ( $tab === 'dashboard' ) : ?>
				<?php
				$coverage_numeric  = max( 0, min( 100, floatval( $stats['coverage'] ) ) );
				$coverage_decimals = $coverage_numeric === floor( $coverage_numeric ) ? 0 : 1;
				$coverage_display  = number_format_i18n( $coverage_numeric, $coverage_decimals );
				/* translators: %s: Percentage value */
				$coverage_text = $coverage_display . '%';
				/* translators: %s: Percentage value */
				$coverage_value_text = sprintf( __( 'ALT coverage at %s', 'beepbeep-ai-alt-text-generator' ), $coverage_text );
				?>

				<?php
				$checkout_nonce = wp_create_nonce( 'bbai_direct_checkout' );
				$checkout_base  = admin_url( 'admin.php' );
				$price_ids      = $this->get_checkout_price_ids();

				$pro_plan         = array(
					'page'        => 'bbai-checkout',
					'plan'        => 'pro',
					'price_id'    => $price_ids['pro'] ?? '',
					'_bbai_nonce' => $checkout_nonce,
				);
				$agency_plan      = array(
					'page'        => 'bbai-checkout',
					'plan'        => 'agency',
					'price_id'    => $price_ids['agency'] ?? '',
					'_bbai_nonce' => $checkout_nonce,
				);
				$credits_plan     = array(
					'page'        => 'bbai-checkout',
					'type'        => 'credits',
					'price_id'    => $price_ids['credits'] ?? '',
					'_bbai_nonce' => $checkout_nonce,
				);
				$pro_test_url     = esc_url( add_query_arg( $pro_plan, $checkout_base ) );
				$agency_test_url  = esc_url( add_query_arg( $agency_plan, $checkout_base ) );
				$credits_test_url = esc_url( add_query_arg( $credits_plan, $checkout_base ) );
				?>

			<div class="bbai-clean-dashboard" data-stats='<?php echo esc_attr( wp_json_encode( $stats ) ); ?>'>
				<?php
				// Get usage stats
				$usage_stats = Usage_Tracker::get_stats_display();

				// Pull fresh usage from backend to avoid stale cache - same logic as Settings tab
				if ( isset( $this->api_client ) ) {
					$live_usage = $this->api_client->get_usage();
					if ( is_array( $live_usage ) && ! empty( $live_usage ) && ! is_wp_error( $live_usage ) ) {
						// Update cache with fresh API data
						Usage_Tracker::update_usage( $live_usage );
					}
				}
				// Get stats - will use the just-updated cache
				$usage_stats     = Usage_Tracker::get_stats_display( false );
				$account_summary = $this->api_client->is_authenticated() ? $this->get_account_summary( $usage_stats ) : null;

				// If stats show 0 but we have API data, use API data directly
				if ( isset( $live_usage ) && is_array( $live_usage ) && ! empty( $live_usage ) && ! is_wp_error( $live_usage ) ) {
					if ( ( $usage_stats['used'] ?? 0 ) == 0 && ( $live_usage['used'] ?? 0 ) > 0 ) {
						// Cache hasn't updated yet, use API data directly
						$usage_stats['used']      = max( 0, intval( $live_usage['used'] ?? 0 ) );
						$usage_stats['limit']     = max( 1, intval( $live_usage['limit'] ?? 50 ) );
						$usage_stats['remaining'] = max( 0, intval( $live_usage['remaining'] ?? 50 ) );
						// Recalculate percentage
						$usage_stats['percentage']         = $usage_stats['limit'] > 0 ? ( ( $usage_stats['used'] / $usage_stats['limit'] ) * 100 ) : 0;
						$usage_stats['percentage']         = min( 100, max( 0, $usage_stats['percentage'] ) );
						$usage_stats['percentage_display'] = Usage_Tracker::format_percentage_label( $usage_stats['percentage'] );
					}
				}

				// Get raw values directly from the stats array - same calculation method as Settings tab
				$dashboard_used      = max( 0, intval( $usage_stats['used'] ?? 0 ) );
				$dashboard_limit     = max( 1, intval( $usage_stats['limit'] ?? 50 ) );
				$dashboard_remaining = max( 0, intval( $usage_stats['remaining'] ?? 50 ) );

				// Recalculate remaining to ensure accuracy
				$dashboard_remaining = max( 0, $dashboard_limit - $dashboard_used );

				// Cap used at limit to prevent showing > 100%
				if ( $dashboard_used > $dashboard_limit ) {
					$dashboard_used      = $dashboard_limit;
					$dashboard_remaining = 0;
				}

				// Calculate percentage - same way as Settings tab
				$percentage = $dashboard_limit > 0 ? ( ( $dashboard_used / $dashboard_limit ) * 100 ) : 0;
				$percentage = min( 100, max( 0, $percentage ) );

				// If at limit, ensure it shows 100%
				if ( $dashboard_used >= $dashboard_limit && $dashboard_remaining <= 0 ) {
					$percentage = 100;
				}

				// Update the stats with calculated values for display
				$usage_stats['used']               = $dashboard_used;
				$usage_stats['limit']              = $dashboard_limit;
				$usage_stats['remaining']          = $dashboard_remaining;
				$usage_stats['percentage']         = $percentage;
				$usage_stats['percentage_display'] = Usage_Tracker::format_percentage_label( $percentage );
				?>
				
				<!-- Clean Dashboard Design -->
				<div class="bbai-dashboard-shell max-w-5xl mx-auto px-6">

					<?php if ( ! $this->api_client->is_authenticated() ) : ?>
					<!-- HERO Section - Not Authenticated -->
					<div class="bbai-hero-section">
						<div class="bbai-hero-content">
							<h2 class="bbai-hero-title">
								<?php esc_html_e( 'Stop Losing Traffic from Google Images — Fix Alt Text Automatically', 'beepbeep-ai-alt-text-generator' ); ?>
							</h2>
							<p class="bbai-hero-subtitle">
								<?php esc_html_e( 'Generate SEO-optimized, WCAG-compliant alt text for every image automatically. Boost Google Images rankings and improve accessibility in seconds. Start with 50 free generations monthly.', 'beepbeep-ai-alt-text-generator' ); ?>
							</p>
						</div>
						<div class="bbai-hero-actions">
							<button type="button" class="bbai-hero-btn-primary" id="bbai-show-auth-banner-btn">
								<?php esc_html_e( 'Get Started Free — Generate 50 AI Alt Texts Now', 'beepbeep-ai-alt-text-generator' ); ?>
							</button>
							<button type="button" class="bbai-hero-link-secondary" id="bbai-show-auth-login-btn">
								<?php esc_html_e( 'Already have an account? Sign in', 'beepbeep-ai-alt-text-generator' ); ?>
							</button>
						</div>
						<div class="bbai-hero-micro-copy">
							<?php esc_html_e( '⚡ SEO Boost · 🦾 Accessibility · 🕒 Saves Hours', 'beepbeep-ai-alt-text-generator' ); ?>
						</div>
					</div>
					<?php endif; ?>
					<!-- Subscription management now in header -->


					<!-- Tab Content: Dashboard -->
					<div class="bbai-tab-content active" id="tab-dashboard">
					<!-- Premium Dashboard Container -->
					<div class="bbai-premium-dashboard">
						<!-- Subtle Header Section -->
						<div class="bbai-dashboard-header-section">
							<h1 class="bbai-dashboard-title"><?php esc_html_e( 'Dashboard', 'beepbeep-ai-alt-text-generator' ); ?></h1>
							<p class="bbai-dashboard-subtitle"><?php esc_html_e( 'Automated, accessible alt text generation for your WordPress media library.', 'beepbeep-ai-alt-text-generator' ); ?></p>
						</div>

						<?php
						$is_authenticated = $this->api_client->is_authenticated();
						$has_license      = $this->api_client->has_active_license();
						if ( $is_authenticated || $has_license ) :
							// Get plan from usage stats or license
							$plan_slug = $usage_stats['plan'] ?? 'free';

							// If using license, check license plan
							if ( $has_license && $plan_slug === 'free' ) {
								$license_data = $this->api_client->get_license_data();
								if ( $license_data && isset( $license_data['organization'] ) ) {
									$plan_slug = strtolower( $license_data['organization']['plan'] ?? 'free' );
								}
							}

							// Determine badge text and class
							$plan_badge_class = 'bbai-usage-plan-badge';
							$is_agency        = ( $plan_slug === 'agency' );
							$is_pro           = ( $plan_slug === 'pro' || $plan_slug === 'agency' );

							if ( $plan_slug === 'agency' ) {
								$plan_badge_text   = esc_html__( 'AGENCY', 'beepbeep-ai-alt-text-generator' );
								$plan_badge_class .= ' bbai-usage-plan-badge--agency';
							} elseif ( $plan_slug === 'pro' ) {
								$plan_badge_text   = esc_html__( 'PRO', 'beepbeep-ai-alt-text-generator' );
								$plan_badge_class .= ' bbai-usage-plan-badge--pro';
							} else {
								$plan_badge_text   = esc_html__( 'FREE', 'beepbeep-ai-alt-text-generator' );
								$plan_badge_class .= ' bbai-usage-plan-badge--free';
							}
							?>
						
						<!-- Multi-User Token Bar Container -->
						<div id="bbai-multiuser-token-bar-root" class="bbai-multiuser-token-bar-container" style="margin-bottom: 24px;"></div>
						
						<!-- Premium Stats Grid -->
						<div class="bbai-premium-stats-grid<?php echo esc_attr( $is_agency ? ' bbai-premium-stats-grid--single' : '' ); ?>">
							<!-- Usage Card with Circular Progress -->
							<div class="bbai-premium-card bbai-usage-card<?php echo esc_attr( $is_agency ? ' bbai-usage-card--full-width' : '' ); ?>">
								<?php if ( $is_agency ) : ?>
								<!-- Soft purple gradient badge for Agency -->
								<span class="bbai-usage-plan-badge bbai-usage-plan-badge--agency-polished"><?php echo esc_html__( 'AGENCY', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<?php else : ?>
								<span class="<?php echo esc_attr( $plan_badge_class ); ?>"><?php echo esc_html( $plan_badge_text ); ?></span>
								<?php endif; ?>
								<?php
								$percentage         = min( 100, max( 0, $usage_stats['percentage'] ?? 0 ) );
								$percentage_display = $usage_stats['percentage_display'] ?? Usage_Tracker::format_percentage_label( $percentage );
								$radius             = 54;
								$circumference      = 2 * M_PI * $radius;
								// Calculate offset: at 0% = full circumference (hidden), at 100% = 0 (fully visible)
								$stroke_dashoffset = $circumference * ( 1 - ( $percentage / 100 ) );
								$gradient_id       = 'grad-' . wp_generate_password( 8, false );
								?>
								<?php if ( $is_agency ) : ?>
								<!-- Full-width agency layout - Polished Design -->
								<div class="bbai-usage-card-layout-full">
									<div class="bbai-usage-card-left">
										<h3 class="bbai-usage-card-title"><?php esc_html_e( 'Alt Text Generated This Month', 'beepbeep-ai-alt-text-generator' ); ?></h3>
										<div class="bbai-usage-card-stats">
											<div class="bbai-usage-stat-item">
												<div class="bbai-usage-stat-value bbai-number-counting"><?php echo esc_html( number_format_i18n( $usage_stats['used'] ) ); ?></div>
												<div class="bbai-usage-stat-label"><?php esc_html_e( 'Generated', 'beepbeep-ai-alt-text-generator' ); ?></div>
											</div>
											<div class="bbai-usage-stat-divider"></div>
											<div class="bbai-usage-stat-item">
												<div class="bbai-usage-stat-value bbai-number-counting"><?php echo esc_html( number_format_i18n( $usage_stats['limit'] ) ); ?></div>
												<div class="bbai-usage-stat-label"><?php esc_html_e( 'Monthly Limit', 'beepbeep-ai-alt-text-generator' ); ?></div>
											</div>
											<div class="bbai-usage-stat-divider"></div>
											<div class="bbai-usage-stat-item">
												<div class="bbai-usage-stat-value bbai-number-counting"><?php echo esc_html( number_format_i18n( $usage_stats['remaining'] ?? 0 ) ); ?></div>
												<div class="bbai-usage-stat-label"><?php esc_html_e( 'Remaining', 'beepbeep-ai-alt-text-generator' ); ?></div>
											</div>
										</div>
										<div class="bbai-usage-card-reset">
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;" aria-hidden="true">
												<circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
												<path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
											</svg>
											<span>
											<?php
											$reset_date = $usage_stats['reset_date'] ?? '';
											if ( ! empty( $reset_date ) ) {
												$reset_timestamp = strtotime( $reset_date );
												if ( $reset_timestamp !== false ) {
													$formatted_date = date_i18n( 'F j, Y', $reset_timestamp );
													printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), esc_html( $formatted_date ) );
												} else {
													printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), esc_html( $reset_date ) );
												}
											} else {
												esc_html_e( 'Resets monthly', 'beepbeep-ai-alt-text-generator' );
											}
											?>
											</span>
										</div>
										<?php
										$plan_slug      = $usage_stats['plan'] ?? 'free';
										$billing_portal = Usage_Tracker::get_billing_portal_url();
										?>
										<?php if ( ! empty( $billing_portal ) ) : ?>
										<div class="bbai-usage-card-actions">
											<a href="#" class="bbai-usage-billing-link" data-action="open-billing-portal">
												<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;" aria-hidden="true">
													<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
													<circle cx="8" cy="8" r="2" fill="currentColor"/>
												</svg>
												<?php esc_html_e( 'Manage Billing', 'beepbeep-ai-alt-text-generator' ); ?>
											</a>
										</div>
										<?php endif; ?>
									</div>
									<div class="bbai-usage-card-divider" aria-hidden="true"></div>
									<div class="bbai-usage-card-right">
								<div class="bbai-usage-ring-wrapper">
											<?php
											// Modern thin stroke ring gauge for agency
											$agency_radius            = 60;
											$agency_circumference     = 2 * M_PI * $agency_radius;
											$agency_stroke_dashoffset = $agency_circumference * ( 1 - ( $percentage / 100 ) );
											$agency_gradient_id       = 'grad-agency-' . wp_generate_password( 8, false );
											?>
											<div class="bbai-circular-progress bbai-circular-progress--agency" 
												data-percentage="<?php echo esc_attr( $percentage ); ?>"
												aria-label="<?php printf( esc_attr__( 'Credits used: %s%%', 'beepbeep-ai-alt-text-generator' ), esc_attr( $percentage_display ) ); ?>"
												role="progressbar"
												aria-valuenow="<?php echo esc_attr( $percentage ); ?>"
												aria-valuemin="0"
												aria-valuemax="100">
												<svg class="bbai-circular-progress-svg" viewBox="0 0 140 140" aria-hidden="true">
													<defs>
														<linearGradient id="<?php echo esc_attr( $agency_gradient_id ); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
															<stop offset="0%" style="stop-color:#9b5cff;stop-opacity:1" />
															<stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1" />
														</linearGradient>
													</defs>
													<!-- Background circle -->
													<circle 
													cx="70" 
													cy="70" 
														r="<?php echo esc_attr( $agency_radius ); ?>" 
													fill="none"
														stroke="#f3f4f6" 
														stroke-width="8" 
														class="bbai-circular-progress-bg" />
													<!-- Progress circle -->
													<circle 
														cx="70" 
														cy="70" 
														r="<?php echo esc_attr( $agency_radius ); ?>" 
														fill="none"
														stroke="url(#<?php echo esc_attr( $agency_gradient_id ); ?>)"
														stroke-width="8"
													stroke-linecap="round"
														stroke-dasharray="<?php echo esc_attr( $agency_circumference ); ?>"
														stroke-dashoffset="<?php echo esc_attr( $agency_stroke_dashoffset ); ?>"
														class="bbai-circular-progress-bar"
														data-circumference="<?php echo esc_attr( $agency_circumference ); ?>"
														data-offset="<?php echo esc_attr( $agency_stroke_dashoffset ); ?>"
														transform="rotate(-90 70 70)" />
												</svg>
												<div class="bbai-circular-progress-text">
													<div class="bbai-circular-progress-percent bbai-number-counting"><?php echo esc_html( $percentage_display ); ?>%</div>
													<div class="bbai-circular-progress-label"><?php esc_html_e( 'Credits Used', 'beepbeep-ai-alt-text-generator' ); ?></div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<?php else : ?>
								<!-- Standard vertical layout -->
								<h3 class="bbai-usage-card-title"><?php esc_html_e( 'Alt Text Generated This Month', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								<div class="bbai-usage-ring-wrapper">
									<div class="bbai-circular-progress" data-percentage="<?php echo esc_attr( $percentage ); ?>">
										<svg class="bbai-circular-progress-svg" viewBox="0 0 120 120">
											<defs>
												<linearGradient id="<?php echo esc_attr( $gradient_id ); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
													<stop offset="0%" style="stop-color:#9b5cff;stop-opacity:1" />
													<stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1" />
												</linearGradient>
											</defs>
											<!-- Background circle -->
											<circle 
												cx="60" 
												cy="60" 
												r="<?php echo esc_attr( $radius ); ?>" 
												fill="none" 
												stroke="#f3f4f6" 
												stroke-width="12" 
												class="bbai-circular-progress-bg" />
											<!-- Progress circle -->
											<circle 
												cx="60" 
												cy="60" 
												r="<?php echo esc_attr( $radius ); ?>" 
												fill="none"
												stroke="url(#<?php echo esc_attr( $gradient_id ); ?>)"
												stroke-width="12"
												stroke-linecap="round"
												stroke-dasharray="<?php echo esc_attr( $circumference ); ?>"
												stroke-dashoffset="<?php echo esc_attr( $stroke_dashoffset ); ?>"
												class="bbai-circular-progress-bar"
												data-circumference="<?php echo esc_attr( $circumference ); ?>"
												data-offset="<?php echo esc_attr( $stroke_dashoffset ); ?>" />
										</svg>
										<div class="bbai-circular-progress-text">
											<div class="bbai-circular-progress-percent"><?php echo esc_html( $percentage_display ); ?>%</div>
											<div class="bbai-circular-progress-label"><?php esc_html_e( 'credits used', 'beepbeep-ai-alt-text-generator' ); ?></div>
										</div>
									</div>
									<button type="button" class="bbai-usage-tooltip" aria-label="<?php esc_attr_e( 'How quotas work', 'beepbeep-ai-alt-text-generator' ); ?>" title="<?php esc_attr_e( 'Your monthly quota resets on the first of each month. Upgrade to Pro for 1,000 generations per month.', 'beepbeep-ai-alt-text-generator' ); ?>">
										<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
											<circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
											<path d="M8 5V5.01M8 11H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
										</svg>
									</button>
								</div>
								<div class="bbai-usage-details">
									<div class="bbai-usage-text">
										<strong class="bbai-number-counting"><?php echo esc_html( $usage_stats['used'] ); ?></strong> / <strong class="bbai-number-counting"><?php echo esc_html( $usage_stats['limit'] ); ?></strong>
									</div>
									<div class="bbai-usage-microcopy">
										<?php
										$reset_date = $usage_stats['reset_date'] ?? '';
										if ( ! empty( $reset_date ) ) {
											// Format as "resets MONTH DAY, YEAR"
											$reset_timestamp = strtotime( $reset_date );
											if ( $reset_timestamp !== false ) {
												$formatted_date = date_i18n( 'F j, Y', $reset_timestamp );
												printf(
													esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ),
													esc_html( $formatted_date )
												);
											} else {
												printf(
													esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ),
													esc_html( $reset_date )
												);
											}
										} else {
											esc_html_e( 'Resets monthly', 'beepbeep-ai-alt-text-generator' );
										}
										?>
									</div>
									<?php
									$plan_slug      = $usage_stats['plan'] ?? 'free';
									$billing_portal = Usage_Tracker::get_billing_portal_url();
									$is_pro         = ( $plan_slug === 'pro' || $plan_slug === 'agency' );
									?>
									<?php if ( ! $is_pro ) : ?>
									<a href="#" class="bbai-usage-upgrade-link" data-action="show-upgrade-modal">
											<?php esc_html_e( 'Upgrade for 1,000 generations monthly', 'beepbeep-ai-alt-text-generator' ); ?> →
									</a>
									<?php elseif ( ! empty( $billing_portal ) ) : ?>
										<a href="#" class="bbai-usage-billing-link" data-action="open-billing-portal">
											<?php esc_html_e( 'Manage billing & invoices', 'beepbeep-ai-alt-text-generator' ); ?> →
										</a>
									<?php endif; ?>
								</div>
								<?php endif; ?>
							</div>
							
							<!-- Premium Upsell Card -->
							<?php if ( ! $is_agency ) : ?>
							<div class="bbai-premium-card bbai-upsell-card">
								<h3 class="bbai-upsell-title"><?php esc_html_e( 'Upgrade to Pro — Unlock 1,000 AI Generations Monthly', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								<ul class="bbai-upsell-features">
									<li>
										<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
											<circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
											<path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
										<?php esc_html_e( '1,000 image generations per month', 'beepbeep-ai-alt-text-generator' ); ?>
									</li>
									<li>
										<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
											<circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
											<path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
										<?php esc_html_e( 'Priority queue processing', 'beepbeep-ai-alt-text-generator' ); ?>
									</li>
									<li>
										<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
											<circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
											<path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
										<?php esc_html_e( 'Bulk optimisation for large libraries', 'beepbeep-ai-alt-text-generator' ); ?>
									</li>
									<li>
										<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
											<circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
											<path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
										<?php esc_html_e( 'Multilingual AI alt text', 'beepbeep-ai-alt-text-generator' ); ?>
									</li>
									<li>
										<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
											<circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
											<path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
										<?php esc_html_e( 'Faster & more descriptive alt text from improved Vision models', 'beepbeep-ai-alt-text-generator' ); ?>
									</li>
								</ul>
								<button type="button" class="bbai-upsell-cta bbai-upsell-cta--large bbai-cta-glow-green" data-action="show-upgrade-modal">
									<?php esc_html_e( 'Pro or Agency', 'beepbeep-ai-alt-text-generator' ); ?>
								</button>
								<p class="bbai-upsell-microcopy">
									<?php esc_html_e( 'Save 15+ hours/month with automated SEO alt generation.', 'beepbeep-ai-alt-text-generator' ); ?>
								</p>
							</div>
							<?php endif; ?>
						</div>
						
						<!-- Stats Cards Row -->
							<?php
							$alt_texts_generated  = $usage_stats['used'] ?? 0;
							$minutes_per_alt_text = 2.5;
							$hours_saved          = round( ( $alt_texts_generated * $minutes_per_alt_text ) / 60, 1 );
							$total_images         = $stats['total'] ?? 0;
							$optimized            = $stats['with_alt'] ?? 0;
							$remaining_images     = $stats['missing'] ?? 0;
							$coverage_percent     = $total_images > 0 ? round( ( $optimized / $total_images ) * 100 ) : 0;
							?>
						<div class="bbai-premium-metrics-grid">
							<!-- Time Saved Card -->
							<div class="bbai-premium-card bbai-metric-card">
								<div class="bbai-metric-icon">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
										<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
										<path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
									</svg>
								</div>
								<div class="bbai-metric-value bbai-number-counting"><?php echo esc_html( $hours_saved ); ?> hrs</div>
								<div class="bbai-metric-label"><?php esc_html_e( 'TIME SAVED', 'beepbeep-ai-alt-text-generator' ); ?></div>
								<div class="bbai-metric-description"><?php esc_html_e( 'vs manual optimisation', 'beepbeep-ai-alt-text-generator' ); ?></div>
							</div>
							
							<!-- Images Optimized Card -->
							<div class="bbai-premium-card bbai-metric-card">
								<div class="bbai-metric-icon">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
										<path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="bbai-metric-value bbai-number-counting"><?php echo esc_html( $optimized ); ?></div>
								<div class="bbai-metric-label"><?php esc_html_e( 'IMAGES OPTIMIZED', 'beepbeep-ai-alt-text-generator' ); ?></div>
								<div class="bbai-metric-description"><?php esc_html_e( 'with generated alt text', 'beepbeep-ai-alt-text-generator' ); ?></div>
							</div>
							
							<!-- Estimated SEO Impact Card -->
							<div class="bbai-premium-card bbai-metric-card">
								<div class="bbai-metric-icon">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
										<path d="M2 12L12 2L22 12M12 8V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="bbai-metric-value bbai-number-counting"><?php echo esc_html( $coverage_percent ); ?>%</div>
								<div class="bbai-metric-label"><?php esc_html_e( 'ESTIMATED SEO IMPACT', 'beepbeep-ai-alt-text-generator' ); ?></div>
							</div>
						</div>
						
						<!-- Site-Wide Licensing Notice -->
						<div class="bbai-premium-card bbai-info-notice">
							<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
								<circle cx="10" cy="10" r="9" stroke="#0ea5e9" stroke-width="1.5" fill="none"/>
								<path d="M10 6V10M10 14H10.01" stroke="#0ea5e9" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
							<span>
								<?php
								$site_name  = trim( get_bloginfo( 'name' ) );
								$site_label = $site_name !== '' ? $site_name : __( 'this WordPress site', 'beepbeep-ai-alt-text-generator' );
								printf(
									esc_html__( 'Monthly quota shared across all users on %s. Upgrade to Pro for 1,000 generations per month.', 'beepbeep-ai-alt-text-generator' ),
									'<strong>' . esc_html( $site_label ) . '</strong>'
								);
								?>
							</span>
						</div>

						<!-- Image Optimization Card (Full Width Pill) -->
							<?php
							$total_images   = $stats['total'] ?? 0;
							$optimized      = $stats['with_alt'] ?? 0;
							$remaining_imgs = $stats['missing'] ?? 0;
							$coverage_pct   = $total_images > 0 ? round( ( $optimized / $total_images ) * 100 ) : 0;

							// Check if user has quota remaining (for free users) or is on pro/agency plan
							$plan         = $usage_stats['plan'] ?? 'free';
							$has_quota    = ( $usage_stats['remaining'] ?? 0 ) > 0;
							$is_premium   = in_array( $plan, array( 'pro', 'agency' ), true );
							$can_generate = $has_quota || $is_premium;
							?>
						<div class="bbai-premium-card bbai-optimization-card <?php echo esc_attr( ( $total_images > 0 && $remaining_imgs === 0 ) ? 'bbai-optimization-card--complete' : '' ); ?>">
							<?php if ( $total_images > 0 && $remaining_imgs === 0 ) : ?>
								<div class="bbai-optimization-accent-bar"></div>
							<?php endif; ?>
							<div class="bbai-optimization-header">
								<?php if ( $total_images > 0 && $remaining_imgs === 0 ) : ?>
									<div class="bbai-optimization-success-chip">
										<svg width="14" height="14" viewBox="0 0 16 16" fill="none">
											<path d="M13 4L6 11L3 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</div>
								<?php endif; ?>
								<h2 class="bbai-optimization-title">
									<?php
									if ( $total_images > 0 ) {
										if ( $remaining_imgs > 0 ) {
											printf(
												esc_html__( '%1$d of %2$d images optimized', 'beepbeep-ai-alt-text-generator' ),
												$optimized,
												$total_images
											);
										} else {
											// Success chip with checkmark icon is already shown above
											printf(
												esc_html__( 'All %1$d images optimized!', 'beepbeep-ai-alt-text-generator' ),
												$total_images
											);
										}
									} else {
										esc_html_e( 'Ready to optimize images', 'beepbeep-ai-alt-text-generator' );
									}
									?>
								</h2>
							</div>
							
							<?php if ( $total_images > 0 ) : ?>
								<div class="bbai-optimization-progress">
									<div class="bbai-optimization-progress-bar">
										<div class="bbai-optimization-progress-fill" style="width: <?php echo esc_attr( $coverage_pct ); ?>%; background: <?php echo esc_attr( ( $remaining_imgs === 0 ) ? '#10b981' : '#9b5cff' ); ?>;"></div>
									</div>
									<div class="bbai-optimization-stats">
										<div class="bbai-optimization-stat">
											<span class="bbai-optimization-stat-label"><?php esc_html_e( 'Optimized', 'beepbeep-ai-alt-text-generator' ); ?></span>
											<span class="bbai-optimization-stat-value"><?php echo esc_html( $optimized ); ?></span>
										</div>
										<div class="bbai-optimization-stat">
											<span class="bbai-optimization-stat-label"><?php esc_html_e( 'Remaining', 'beepbeep-ai-alt-text-generator' ); ?></span>
											<span class="bbai-optimization-stat-value"><?php echo esc_html( $remaining_imgs ); ?></span>
										</div>
										<div class="bbai-optimization-stat">
											<span class="bbai-optimization-stat-label"><?php esc_html_e( 'Total', 'beepbeep-ai-alt-text-generator' ); ?></span>
											<span class="bbai-optimization-stat-value"><?php echo esc_html( $total_images ); ?></span>
										</div>
									</div>
									<div class="bbai-optimization-actions">
										<button type="button" class="bbai-optimization-cta bbai-optimization-cta--primary <?php echo esc_attr( ( ! $can_generate ) ? 'bbai-optimization-cta--locked' : '' ); ?>" data-action="generate-missing" <?php echo ( ! $can_generate ) ? 'disabled title="' . esc_attr__( 'Unlock 1,000 alt text generations with Pro →', 'beepbeep-ai-alt-text-generator' ) . '"' : ''; ?>>
											<?php if ( ! $can_generate ) : ?>
												<svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
													<path d="M12 6V4a4 4 0 00-8 0v2M4 6h8l1 8H3L4 6z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
												</svg>
												<span><?php esc_html_e( 'Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator' ); ?></span>
											<?php else : ?>
												<svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
													<rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
													<path d="M6 6H10M6 10H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
												</svg>
												<span><?php esc_html_e( 'Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator' ); ?></span>
											<?php endif; ?>
										</button>
										<button type="button" class="bbai-optimization-cta bbai-optimization-cta--secondary bbai-cta-glow-blue <?php echo esc_attr( ( ! $can_generate ) ? 'bbai-optimization-cta--locked' : '' ); ?>" data-action="regenerate-all" <?php echo ( ! $can_generate ) ? 'disabled title="' . esc_attr__( 'Unlock 1,000 alt text generations with Pro →', 'beepbeep-ai-alt-text-generator' ) . '"' : ''; ?>>
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
												<path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
												<circle cx="8" cy="8" r="2" fill="currentColor"/>
											</svg>
											<span><?php esc_html_e( 'Re-optimize All Alt Text', 'beepbeep-ai-alt-text-generator' ); ?></span>
										</button>
									</div>
								</div>
							<?php else : ?>
								<div class="bbai-optimization-empty">
									<p><?php esc_html_e( 'Upload images to your Media Library and generate SEO-optimized alt text automatically. Every image gets WCAG-compliant descriptions that boost Google Images rankings.', 'beepbeep-ai-alt-text-generator' ); ?></p>
									<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="bbai-btn-primary">
										<?php esc_html_e( 'Go to Media Library', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
								</div>
							<?php endif; ?>
						</div>
						
						<!-- Footer Cross-Sell -->
						<div class="bbai-premium-footer-cta">
							<p class="bbai-footer-cta-text">
								<?php esc_html_e( 'Complete your SEO stack', 'beepbeep-ai-alt-text-generator' ); ?> 
								<a href="https://oppti.dev/plugins/meta" target="_blank" rel="noopener noreferrer" class="bbai-footer-cta-link bbai-footer-cta-link--coming-soon">
									<?php esc_html_e( 'Try our SEO Meta Generator AI', 'beepbeep-ai-alt-text-generator' ); ?>
									<span class="bbai-footer-cta-badge-new"><?php esc_html_e( 'New', 'beepbeep-ai-alt-text-generator' ); ?></span>
									<span class="bbai-footer-cta-badge-coming-soon"><?php esc_html_e( 'Coming Soon', 'beepbeep-ai-alt-text-generator' ); ?></span>
								</a>
								<span class="bbai-footer-cta-badge"><?php esc_html_e( '(included in free plan)', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</p>
						</div>
						
						<!-- Powered by OpttiAI -->
						<div class="bbai-premium-footer-divider"></div>
						<div class="bbai-premium-footer-branding">
							<?php
							$site_url = 'https://oppti.dev';
							if ( function_exists( 'opptiai_framework' ) && opptiai_framework()->config ) {
								$site_url = opptiai_framework()->config->get( 'site', 'https://oppti.dev' );
							}
							?>
							<span><?php esc_html_e( 'Powered by', 'beepbeep-ai-alt-text-generator' ); ?> <strong><a href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener" style="color: inherit; text-decoration: none;">OpttiAI</a></strong></span>
						</div>
						
						<!-- Circular Progress Animation Script -->
						<script>
						(function() {
							function initProgressRings() {
								var rings = document.querySelectorAll('.bbai-circular-progress-bar[data-offset]');
								rings.forEach(function(ring) {
									var circumference = parseFloat(ring.getAttribute('data-circumference'));
									var targetOffset = parseFloat(ring.getAttribute('data-offset'));
									
									if (!isNaN(circumference) && !isNaN(targetOffset)) {
										// Start from full (hidden)
										ring.style.strokeDashoffset = circumference;
										ring.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1)';
										
										// Animate to target
										requestAnimationFrame(function() {
											ring.style.strokeDashoffset = targetOffset;
										});
									}
								});
							}
							
							if (document.readyState === 'loading') {
								document.addEventListener('DOMContentLoaded', initProgressRings);
							} else {
								setTimeout(initProgressRings, 50);
							}
						})();
						</script>
					<?php else : ?>
						<!-- Not Authenticated - Demo Preview (Using Real Dashboard Structure) -->
						<div class="bbai-demo-preview">
							<!-- Demo Badge Overlay -->
							<div class="bbai-demo-badge-overlay">
								<span class="bbai-demo-badge-text"><?php esc_html_e( 'DEMO PREVIEW', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							
							<!-- Usage Card (Demo) -->
							<div class="bbai-dashboard-card bbai-dashboard-card--featured bbai-demo-mode">
								<div class="bbai-dashboard-card-header">
									<div class="bbai-dashboard-card-badge"><?php esc_html_e( 'USAGE STATUS', 'beepbeep-ai-alt-text-generator' ); ?></div>
									<h2 class="bbai-dashboard-card-title">
										<span class="bbai-dashboard-emoji">📊</span>
										<?php esc_html_e( '0 of 50 image descriptions generated this month.', 'beepbeep-ai-alt-text-generator' ); ?>
									</h2>
									<p style="margin: 12px 0 0 0; font-size: 14px; color: #6b7280;">
										<?php esc_html_e( 'Sign in to track your usage and access premium features.', 'beepbeep-ai-alt-text-generator' ); ?>
									</p>
								</div>
								
								<div class="bbai-dashboard-usage-bar">
									<div class="bbai-dashboard-usage-bar-fill" style="width: 0%;"></div>
								</div>
								
								<div class="bbai-dashboard-usage-stats">
									<div class="bbai-dashboard-usage-stat">
										<span class="bbai-dashboard-usage-label"><?php esc_html_e( 'Used', 'beepbeep-ai-alt-text-generator' ); ?></span>
										<span class="bbai-dashboard-usage-value">0</span>
									</div>
									<div class="bbai-dashboard-usage-stat">
										<span class="bbai-dashboard-usage-label"><?php esc_html_e( 'Remaining', 'beepbeep-ai-alt-text-generator' ); ?></span>
										<span class="bbai-dashboard-usage-value">50</span>
									</div>
									<div class="bbai-dashboard-usage-stat">
										<span class="bbai-dashboard-usage-label"><?php esc_html_e( 'Resets', 'beepbeep-ai-alt-text-generator' ); ?></span>
										<span class="bbai-dashboard-usage-value"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( 'first day of next month' ) ) ); ?></span>
									</div>
								</div>
							</div>

							<!-- Time Saved Card (Demo) -->
							<div class="bbai-dashboard-card bbai-time-saved-card bbai-demo-mode">
								<div class="bbai-dashboard-card-header">
									<div class="bbai-dashboard-card-badge"><?php esc_html_e( 'TIME SAVED', 'beepbeep-ai-alt-text-generator' ); ?></div>
									<h2 class="bbai-dashboard-card-title">
										<span class="bbai-dashboard-emoji">⏱️</span>
										<?php esc_html_e( 'Ready to optimize your images', 'beepbeep-ai-alt-text-generator' ); ?>
									</h2>
									<p class="bbai-seo-impact" style="margin-top: 8px; font-size: 14px; color: #6b7280;"><?php esc_html_e( 'Start generating alt text to improve SEO and accessibility', 'beepbeep-ai-alt-text-generator' ); ?></p>
								</div>
							</div>

							<!-- Image Optimization Card (Demo) -->
							<div class="bbai-dashboard-card bbai-demo-mode">
								<div class="bbai-dashboard-card-header">
									<div class="bbai-dashboard-card-badge"><?php esc_html_e( 'IMAGE OPTIMIZATION', 'beepbeep-ai-alt-text-generator' ); ?></div>
									<h2 class="bbai-dashboard-card-title">
										<span class="bbai-dashboard-emoji">📊</span>
										<?php esc_html_e( 'Ready to optimize images', 'beepbeep-ai-alt-text-generator' ); ?>
									</h2>
								</div>
								
								<div class="bbai-dashboard-usage-bar">
									<div class="bbai-dashboard-usage-bar-fill" style="width: 0%;"></div>
								</div>
								
								<div class="bbai-dashboard-usage-stats">
									<div class="bbai-dashboard-usage-stat">
										<span class="bbai-dashboard-usage-label"><?php esc_html_e( 'Optimized', 'beepbeep-ai-alt-text-generator' ); ?></span>
										<span class="bbai-dashboard-usage-value">0</span>
									</div>
									<div class="bbai-dashboard-usage-stat">
										<span class="bbai-dashboard-usage-label"><?php esc_html_e( 'Remaining', 'beepbeep-ai-alt-text-generator' ); ?></span>
										<span class="bbai-dashboard-usage-value">—</span>
									</div>
									<div class="bbai-dashboard-usage-stat">
										<span class="bbai-dashboard-usage-label"><?php esc_html_e( 'Total', 'beepbeep-ai-alt-text-generator' ); ?></span>
										<span class="bbai-dashboard-usage-value">—</span>
									</div>
								</div>
							</div>
							
							<!-- Demo CTA -->
							<div class="bbai-demo-cta">
								<p class="bbai-demo-cta-text"><?php esc_html_e( '✨ Sign up now to start generating alt text for your images!', 'beepbeep-ai-alt-text-generator' ); ?></p>
								<button type="button" class="bbai-btn-primary bbai-btn-icon" id="bbai-demo-signup-btn">
									<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
										<path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
									</svg>
									<span><?php esc_html_e( 'Generate 50 AI Alt Texts Free', 'beepbeep-ai-alt-text-generator' ); ?></span>
								</button>
							</div>
						</div>
					<?php endif; // End is_authenticated check for usage/stats cards ?>

					<?php if ( $this->api_client->is_authenticated() && ( $usage_stats['remaining'] ?? 0 ) <= 0 ) : ?>
						<div class="bbai-limit-reached">
							<div class="bbai-limit-header-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none">
									<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</div>
							<h3 class="bbai-limit-title"><?php esc_html_e( 'Monthly quota reached — upgrade to Pro for 1,000 generations per month', 'beepbeep-ai-alt-text-generator' ); ?></h3>
							<p class="bbai-limit-description">
								<?php
									$reset_label = $usage_stats['reset_date'] ?? '';
									printf(
										esc_html__( 'You\'ve used all %1$d free generations this month. Your quota resets on %2$s.', 'beepbeep-ai-alt-text-generator' ),
										$usage_stats['limit'],
										esc_html( $reset_label )
									);
								?>
							</p>

							<div class="bbai-countdown" data-countdown="<?php echo esc_attr( $usage_stats['seconds_until_reset'] ?? 0 ); ?>" data-reset-timestamp="<?php echo esc_attr( $usage_stats['reset_timestamp'] ?? 0 ); ?>">
								<div class="bbai-countdown-item">
									<div class="bbai-countdown-number" data-days>0</div>
									<div class="bbai-countdown-label"><?php esc_html_e( 'days', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
								<div class="bbai-countdown-separator">—</div>
								<div class="bbai-countdown-item">
									<div class="bbai-countdown-number" data-hours>0</div>
									<div class="bbai-countdown-label"><?php esc_html_e( 'hours', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
								<div class="bbai-countdown-separator">—</div>
								<div class="bbai-countdown-item">
									<div class="bbai-countdown-number" data-minutes>0</div>
									<div class="bbai-countdown-label"><?php esc_html_e( 'mins', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>

							<div class="bbai-limit-cta">
								<button type="button" class="bbai-limit-upgrade-btn bbai-limit-upgrade-btn--full" data-action="show-upgrade-modal" data-upgrade-source="upgrade-modal">
									<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?>
								</button>
							</div>
						</div>
					<?php endif; ?>

					<!-- Testimonial Block -->
					<div class="bbai-testimonials-grid">
						<div class="bbai-testimonial-block">
							<div class="bbai-testimonial-stars">⭐️⭐️⭐️⭐️⭐️</div>
							<blockquote class="bbai-testimonial-quote">
								<?php esc_html_e( '"Generated 1,200 alt texts for our agency in minutes."', 'beepbeep-ai-alt-text-generator' ); ?>
							</blockquote>
							<div class="bbai-testimonial-author-wrapper">
								<div class="bbai-testimonial-avatar">SW</div>
								<cite class="bbai-testimonial-author"><?php esc_html_e( 'Sarah W.', 'beepbeep-ai-alt-text-generator' ); ?></cite>
							</div>
						</div>
						<div class="bbai-testimonial-block">
							<div class="bbai-testimonial-stars">⭐️⭐️⭐️⭐️⭐️</div>
							<blockquote class="bbai-testimonial-quote">
								<?php esc_html_e( '"We automated 4,800 alt text entries for our WooCommerce store."', 'beepbeep-ai-alt-text-generator' ); ?>
							</blockquote>
							<div class="bbai-testimonial-author-wrapper">
								<div class="bbai-testimonial-avatar">MP</div>
								<cite class="bbai-testimonial-author"><?php esc_html_e( 'Martin P.', 'beepbeep-ai-alt-text-generator' ); ?></cite>
							</div>
						</div>
					</div>

					</div> <!-- End Dashboard Container -->
					</div> <!-- End Premium Dashboard -->
					</div> <!-- End Tab Content: Dashboard -->
					
					<script>
					(function($) {
						'use strict';
						
						/**
						 * Animate a single number element
						 * Marks element as animated to prevent re-animation
						 */
						function animateNumberElement($el, delay) {
							// Skip if already animated
							if ($el.data('bbai-animated')) return;
							// Mark as animated immediately to prevent re-animation
							$el.data('bbai-animated', true);
							
							var originalValue = $el.text().trim();
							
							// Skip if empty
							if (!originalValue) return;
							
							// Extract numeric value - handle percentages, decimals, and "hrs" suffix
							var numericMatch = originalValue.match(/[\d,]+\.?\d*/);
							if (!numericMatch) return;
							
							var finalValue = parseFloat(numericMatch[0].replace(/,/g, ''));
							if (isNaN(finalValue)) return;
							
							// Store original formatting
							var hasPercent = originalValue.indexOf('%') !== -1;
							var hasHrs = originalValue.toLowerCase().indexOf('hrs') !== -1;
							var suffix = '';
							if (hasPercent) suffix = '%';
							else if (hasHrs) suffix = ' hrs';
							
							// Animate from 0 to final value
							var duration = 1200; // 1.2 seconds
							var startTime = null;
							var startValue = 0;
							
							function animate(currentTime) {
								if (!startTime) startTime = currentTime;
								var progress = Math.min((currentTime - startTime) / duration, 1);
								
								// Easing function (ease-out cubic)
								var easeProgress = 1 - Math.pow(1 - progress, 3);
								var currentValue = startValue + (finalValue - startValue) * easeProgress;
								
								// Format number based on original format
								var formatted;
								if (finalValue % 1 === 0) {
									formatted = Math.round(currentValue).toLocaleString();
								} else {
									formatted = currentValue.toFixed(1).toLocaleString();
								}
								
								// Add back suffix
								formatted += suffix;
								
								$el.text(formatted);
								
								if (progress < 1) {
									requestAnimationFrame(animate);
								} else {
									// Ensure final value is exact
									$el.text(originalValue);
								}
							}
							
							// Trigger animation with delay for stagger effect
							setTimeout(function() {
								if ($el.is(':visible')) {
									requestAnimationFrame(animate);
								}
							}, delay || 0);
						}
						
						/**
						 * Set up scroll-triggered number animations using IntersectionObserver
						 * Only animates once per page load, not when switching browser tabs
						 */
						function initScrollAnimations() {
							// Check if page was hidden (user switched tabs) - don't re-animate
							if (typeof document !== 'undefined' && document.hidden) {
								return; // Page is hidden, don't initialize animations
							}
							
							// Check if IntersectionObserver is supported
							if (typeof IntersectionObserver === 'undefined') {
								// Fallback: animate all visible numbers on page load (only if not already animated)
								$('.bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-number-counting, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').each(function(index) {
									var $el = $(this);
									// Only animate if not already animated
									if (!$el.data('bbai-animated')) {
										animateNumberElement($el, index * 50);
									}
								});
								return;
							}
							
							// Find all number elements that haven't been animated yet
							var $numberElements = $('.bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-number-counting, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').filter(function() {
								return !$(this).data('bbai-animated');
							});
							
							if ($numberElements.length === 0) return;
							
							// Create IntersectionObserver
							var observer = new IntersectionObserver(function(entries) {
								entries.forEach(function(entry, index) {
									if (entry.isIntersecting) {
										var $el = $(entry.target);
										// Double-check it hasn't been animated (in case of race condition)
										if ($el.data('bbai-animated')) {
											observer.unobserve(entry.target);
											return;
										}
										var delay = index * 50; // Stagger animations
										animateNumberElement($el, delay);
										// Unobserve after animation triggers
										observer.unobserve(entry.target);
									}
								});
							}, {
								// Trigger when element is 10% visible
								threshold: 0.1,
								// Start observing slightly before element enters viewport
								rootMargin: '0px 0px -50px 0px'
							});
							
							// Observe all number elements
							$numberElements.each(function() {
								observer.observe(this);
							});
						}
						
						/**
						 * Animate numbers when they come into view (for compatibility)
						 */
						function animateNumbers() {
							// Use the new scroll-triggered animation system
							initScrollAnimations();
						}
						
						/**
						 * Add glow classes to CTA buttons on page load
						 */
						function initGlowButtons() {
							// Ensure main CTA buttons have glow
							$('.bbai-upsell-cta--large').addClass('bbai-cta-glow-green');
							$('.bbai-optimization-btn-secondary').addClass('bbai-cta-glow-blue');
							$('.button.button-primary').addClass('bbai-cta-glow');
						}
						
						// Track if animations have been initialized for this page load
						var animationsInitialized = false;
						
						// Run on document ready (only once per page load)
						$(document).ready(function() {
							// Only initialize if page is visible (not in background tab)
							if (typeof document !== 'undefined' && !document.hidden) {
								// Small delay to ensure DOM is ready
								setTimeout(function() {
									if (!animationsInitialized) {
										animateNumbers();
										initGlowButtons();
										animationsInitialized = true;
									}
								}, 100);
							}
						});
						
						// Prevent re-animation when switching browser tabs
						if (typeof document !== 'undefined') {
							document.addEventListener('visibilitychange', function() {
								// If page becomes hidden, mark that we should not re-animate
								if (document.hidden) {
									// Page is now hidden - don't do anything
									return;
								}
								// Page became visible again - but don't re-animate if already initialized
								if (!animationsInitialized) {
									// Only initialize if it's the first time (page was loaded in background)
									setTimeout(function() {
										animateNumbers();
										initGlowButtons();
										animationsInitialized = true;
									}, 100);
								}
							});
						}
						
						// Re-run on plugin tab navigation (internal tab switch within plugin)
						$(document).on('click', '.bbai-nav-link', function() {
							setTimeout(function() {
								// Only reset animation flag for elements in the NEW tab content
								// Find the tab container to scope the reset
								var $targetTab = $('.bbai-tab-content[data-tab]');
								if ($targetTab.length) {
									// Reset animation flags only for elements in visible/new tab
									$targetTab.find('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').removeData('bbai-animated');
									// Reinitialize scroll animations for new tab content
									initScrollAnimations();
									initGlowButtons();
								}
							}, 300);
						});
						
						// Also run when page loads with dashboard tab active (initial load only)
						if (window.location.search.indexOf('tab=dashboard') !== -1 || !window.location.search) {
							setTimeout(function() {
								if (!animationsInitialized) {
									initScrollAnimations();
									initGlowButtons();
									animationsInitialized = true;
								}
							}, 200);
						}
						
						// Reinitialize on window resize (in case layout changes) - only for unanimated elements
						var resizeTimer;
						$(window).on('resize', function() {
							clearTimeout(resizeTimer);
							resizeTimer = setTimeout(function() {
								// Only reinitialize if numbers haven't been animated yet
								var $unanimatedNumbers = $('.bbai-number-counting, .bbai-metric-value, .bbai-card-value, .bbai-usage-value, .bbai-usage-stat-value, .bbai-optimization-stat-value, .bbai-circular-progress-percent, .bbai-usage-text strong').filter(function() {
									return !$(this).data('bbai-animated');
								});
								if ($unanimatedNumbers.length > 0) {
									initScrollAnimations();
								}
							}, 250);
						});
					})(jQuery);
					</script>

			<?php elseif ( $tab === 'library' && $this->api_client->is_authenticated() ) : ?>
				<!-- ALT Library Table -->
				<?php
				// Pagination setup
				$per_page     = 10;
				$alt_page_raw = isset( $_GET['alt_page'] ) ? wp_unslash( $_GET['alt_page'] ) : '';
				$current_page = max( 1, absint( $alt_page_raw ) );
				$offset       = ( $current_page - 1 ) * $per_page;

				// Get all images (with or without alt text) for proper filtering
				global $wpdb;

				// Get total count of all images
				$total_images = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'inherit' AND post_mime_type LIKE %s",
						'attachment',
						'image/%'
					)
				);

				// Get images with alt text count
				$with_alt_count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT p.ID)
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = %s
                     AND p.post_mime_type LIKE %s
                     AND p.post_status = %s
                     AND pm.meta_key = %s
                     AND TRIM(pm.meta_value) <> ''",
						'attachment',
						'image/%',
						'inherit',
						'_wp_attachment_image_alt'
					)
				);

				// Calculate optimization percentage
				$optimization_percentage = $total_images > 0 ? round( ( $with_alt_count / $total_images ) * 100 ) : 0;

				// Get all images with their alt text status
				$all_images = $wpdb->get_results(
					$wpdb->prepare(
						"
                    SELECT p.*, 
                           COALESCE(pm.meta_value, '') as alt_text,
                           CASE WHEN pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> '' THEN 1 ELSE 0 END as has_alt
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND p.post_status = 'inherit'
                    ORDER BY p.post_date DESC
                    LIMIT %d OFFSET %d
                ",
						$per_page,
						$offset
					)
				);

				// CRITICAL: Debug - log image IDs to verify they're different
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					$image_ids = array_map(
						function ( $img ) {
							return $img->ID;
						},
						$all_images
					);
					error_log( 'BBAI ALT Library: Found ' . count( $all_images ) . ' images. IDs: ' . implode( ', ', $image_ids ) );
				}

				$total_count      = $total_images;
				$image_count      = count( $all_images );
				$total_pages      = ceil( $total_count / $per_page );
				$optimized_images = $all_images; // Use all_images for the table

				// Get plan info for upgrade card - check license first
				$has_license  = $this->api_client->has_active_license();
				$license_data = $this->api_client->get_license_data();
				$plan_slug    = isset( $usage_stats ) && isset( $usage_stats['plan'] ) ? $usage_stats['plan'] : 'free';

				// If using license, check license plan
				if ( $has_license && $license_data && isset( $license_data['organization'] ) ) {
					$license_plan = strtolower( $license_data['organization']['plan'] ?? 'free' );
					if ( $license_plan !== 'free' ) {
						$plan_slug = $license_plan;
					}
				}

				$is_pro    = ( $plan_slug === 'pro' || $plan_slug === 'agency' );
				$is_agency = ( $plan_slug === 'agency' );

				?>
				<div class="bbai-dashboard-container">
					<!-- Header Section -->
					<div class="bbai-library-header">
						<h1 class="bbai-library-title"><?php esc_html_e( 'Image Alt Text Library', 'beepbeep-ai-alt-text-generator' ); ?></h1>
						<p class="bbai-library-subtitle"><?php esc_html_e( 'Browse, search, and regenerate SEO-optimized alt text for all images in your media library. Boost Google Images rankings and improve accessibility instantly.', 'beepbeep-ai-alt-text-generator' ); ?></p>
						
						<!-- Optimization Notice -->
						<?php if ( $optimization_percentage >= 100 ) : ?>
							<div class="bbai-library-success-notice">
								<span class="bbai-library-success-text">
									<?php esc_html_e( '100% of your library is fully optimized — great progress!', 'beepbeep-ai-alt-text-generator' ); ?>
								</span>
								<?php if ( ! $is_pro ) : ?>
									<button type="button" class="bbai-library-success-btn" data-action="show-upgrade-modal">
										<?php esc_html_e( 'Pro or Agency', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<div class="bbai-library-notice">
								<?php
								printf(
									esc_html__( '%1$d%% of your library is fully optimized — great progress!', 'beepbeep-ai-alt-text-generator' ),
									$optimization_percentage
								);
								?>
								<?php if ( ! $is_pro ) : ?>
									<button type="button" class="bbai-library-notice-link" data-action="show-upgrade-modal">
										<?php esc_html_e( 'Pro or Agency', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
					
					<!-- Search and Filters Row -->
					<div class="bbai-library-controls">
						<div class="bbai-library-search-wrapper">
							<svg class="bbai-library-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
								<circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
								<path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
							<input type="text" 
									id="bbai-library-search" 
									class="bbai-library-search-input" 
									placeholder="<?php esc_attr_e( 'Search images or alt text…', 'beepbeep-ai-alt-text-generator' ); ?>"
							/>
						</div>
						<select id="bbai-status-filter" class="bbai-library-filter-select">
							<option value="all"><?php esc_html_e( 'All', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="optimized"><?php esc_html_e( 'Optimized', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="missing"><?php esc_html_e( 'Missing ALT', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="errors"><?php esc_html_e( 'Errors', 'beepbeep-ai-alt-text-generator' ); ?></option>
						</select>
						<select id="bbai-time-filter" class="bbai-library-filter-select">
							<option value="this-month"><?php esc_html_e( 'This month', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="last-month"><?php esc_html_e( 'Last month', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="all-time"><?php esc_html_e( 'All time', 'beepbeep-ai-alt-text-generator' ); ?></option>
						</select>
					</div>
					
					<!-- Table Card - Full Width -->
					<div class="bbai-library-table-card<?php echo esc_attr( $is_agency ? ' bbai-library-table-card--full-width' : '' ); ?>">
						<div class="bbai-table-scroll">
							<table class="bbai-library-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'IMAGE', 'beepbeep-ai-alt-text-generator' ); ?></th>
										<th><?php esc_html_e( 'STATUS', 'beepbeep-ai-alt-text-generator' ); ?></th>
										<th><?php esc_html_e( 'DATE', 'beepbeep-ai-alt-text-generator' ); ?></th>
										<th><?php esc_html_e( 'ALT TEXT', 'beepbeep-ai-alt-text-generator' ); ?></th>
										<th><?php esc_html_e( 'ACTIONS', 'beepbeep-ai-alt-text-generator' ); ?></th>
									</tr>
								</thead>
						<tbody>
							<?php if ( ! empty( $optimized_images ) ) : ?>
								<?php $row_index = 0; ?>
								<?php foreach ( $optimized_images as $image ) : ?>
									<?php
									$attachment_id        = $image->ID;
									$old_alt              = get_post_meta( $attachment_id, '_bbai_original', true ) ?: '';
									$current_alt          = $image->alt_text ?? get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
									$thumb_url            = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
									$attachment_title_raw = get_the_title( $attachment_id );
									$attachment_title     = is_string( $attachment_title_raw ) ? $attachment_title_raw : '';
									$edit_link            = get_edit_post_link( $attachment_id, '' );

									$clean_current_alt = is_string( $current_alt ) ? trim( $current_alt ) : '';
									$clean_old_alt     = is_string( $old_alt ) ? trim( $old_alt ) : '';
									$has_alt           = ! empty( $clean_current_alt );

									$status_key   = $has_alt ? 'optimized' : 'missing';
									$status_label = $has_alt ? __( '✅ Optimized', 'beepbeep-ai-alt-text-generator' ) : __( '🟠 Missing', 'beepbeep-ai-alt-text-generator' );
									if ( $has_alt && $clean_old_alt && strcasecmp( $clean_old_alt, $clean_current_alt ) !== 0 ) {
										$status_key   = 'regenerated';
										$status_label = __( '🔁 Regenerated', 'beepbeep-ai-alt-text-generator' );
									}

									// Get the date alt text was generated (use modified date)
									$post          = get_post( $attachment_id );
									$modified_date = $post ? get_the_modified_date( 'M j, Y', $post ) : '';
									$modified_time = $post ? get_the_modified_time( 'g:i a', $post ) : '';

									++$row_index;

									// Truncate alt text to 55 characters
									$truncated_alt = $clean_current_alt;
									if ( strlen( $truncated_alt ) > 55 ) {
										$truncated_alt = substr( $truncated_alt, 0, 55 ) . '...';
									}

									// Status badge class names for CSS styling
									$status_class = 'bbai-status-badge';
									if ( $status_key === 'optimized' ) {
										$status_class .= ' bbai-status-badge--optimized';
									} elseif ( $status_key === 'missing' ) {
										$status_class .= ' bbai-status-badge--missing';
									} else {
										$status_class .= ' bbai-status-badge--regenerated';
									}
									?>
									<tr class="bbai-library-row" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>" data-status="<?php echo esc_attr( $status_key ); ?>">
										<td class="bbai-library-cell bbai-library-cell--image">
											<?php if ( $thumb_url ) : ?>
												<img src="<?php echo esc_url( $thumb_url[0] ); ?>" alt="<?php echo esc_attr( $attachment_title ); ?>" class="bbai-library-thumbnail" />
											<?php else : ?>
												<div class="bbai-library-thumbnail-placeholder">
													<?php esc_html_e( '—', 'beepbeep-ai-alt-text-generator' ); ?>
												</div>
											<?php endif; ?>
										</td>
										<td class="bbai-library-cell bbai-library-cell--status">
											<span class="<?php echo esc_attr( $status_class ); ?>">
												<?php if ( $status_key === 'optimized' ) : ?>
													<?php esc_html_e( 'Optimised', 'beepbeep-ai-alt-text-generator' ); ?>
												<?php elseif ( $status_key === 'missing' ) : ?>
													<?php esc_html_e( 'Missing', 'beepbeep-ai-alt-text-generator' ); ?>
												<?php else : ?>
													<?php esc_html_e( 'Regenerated', 'beepbeep-ai-alt-text-generator' ); ?>
												<?php endif; ?>
											</span>
										</td>
										<td class="bbai-library-cell bbai-library-cell--date">
											<?php echo esc_html( $modified_date ?: '—' ); ?>
										</td>
										<td class="bbai-library-cell bbai-library-cell--alt-text new-alt-cell-<?php echo esc_attr( $attachment_id ); ?>">
											<?php if ( $has_alt ) : ?>
												<div class="bbai-library-alt-text" title="<?php echo esc_attr( $clean_current_alt ); ?>">
													<?php echo esc_html( $truncated_alt ); ?>
												</div>
											<?php else : ?>
												<span class="bbai-library-no-alt"><?php esc_html_e( 'No alt text', 'beepbeep-ai-alt-text-generator' ); ?></span>
											<?php endif; ?>
										</td>
										<td class="bbai-library-cell bbai-library-cell--actions">
											<?php
											$is_local_dev   = defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV;
											$can_regenerate = $is_local_dev || $this->api_client->is_authenticated();
											?>
											<?php
											// CRITICAL: Debug - log attachment_id when rendering button to verify it's different for each row
											if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
												error_log( 'BBAI ALT Library: Rendering regenerate button for attachment_id: ' . $attachment_id );
											}
											?>
											<button type="button" 
													class="bbai-btn-regenerate" 
													data-action="regenerate-single" 
													data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>"
													data-image-id="<?php echo esc_attr( $attachment_id ); ?>"
													data-id="<?php echo esc_attr( $attachment_id ); ?>"
													id="bbai-regenerate-btn-<?php echo esc_attr( $attachment_id ); ?>"
													<?php echo ! $can_regenerate ? 'disabled title="' . esc_attr__( 'Please log in to regenerate alt text', 'beepbeep-ai-alt-text-generator' ) . '"' : ''; ?>>
												<?php esc_html_e( 'Regenerate', 'beepbeep-ai-alt-text-generator' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr>
									<td colspan="5" class="bbai-library-empty-state">
										<div class="bbai-library-empty-content">
											<div class="bbai-library-empty-title">
												<?php esc_html_e( 'No images found', 'beepbeep-ai-alt-text-generator' ); ?>
											</div>
											<div class="bbai-library-empty-subtitle">
												<?php esc_html_e( 'Upload images to your Media Library to get started.', 'beepbeep-ai-alt-text-generator' ); ?>
											</div>
											<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="bbai-library-empty-btn">
												<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
													<path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
												</svg>
												<?php esc_html_e( 'Upload Images', 'beepbeep-ai-alt-text-generator' ); ?>
											</a>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
					
					<!-- Pagination -->
					<?php if ( $total_pages > 1 ) : ?>
						<div class="bbai-pagination">
							<div class="bbai-pagination-info">
								<?php
								$start = $offset + 1;
								$end   = min( $offset + $per_page, $total_count );
								printf(
									esc_html__( 'Showing %1$d-%2$d of %3$d images', 'beepbeep-ai-alt-text-generator' ),
									$start,
									$end,
									$total_count
								);
								?>
							</div>
							
							<div class="bbai-pagination-controls">
								<?php if ( $current_page > 1 ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'alt_page', 1 ) ); ?>" class="bbai-pagination-btn bbai-pagination-btn--first" title="<?php esc_attr_e( 'First page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'First', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
									<a href="<?php echo esc_url( add_query_arg( 'alt_page', $current_page - 1 ) ); ?>" class="bbai-pagination-btn bbai-pagination-btn--prev" title="<?php esc_attr_e( 'Previous page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
								<?php else : ?>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'First', 'beepbeep-ai-alt-text-generator' ); ?></span>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<?php endif; ?>
								
								<div class="bbai-pagination-pages">
									<?php
									$start_page = max( 1, $current_page - 2 );
									$end_page   = min( $total_pages, $current_page + 2 );

									if ( $start_page > 1 ) {
										echo '<a href="' . esc_url( add_query_arg( 'alt_page', 1 ) ) . '" class="bbai-pagination-btn">1</a>';
										if ( $start_page > 2 ) {
											echo '<span class="bbai-pagination-ellipsis">...</span>';
										}
									}

									for ( $i = $start_page; $i <= $end_page; $i++ ) {
										if ( $i == $current_page ) {
											echo '<span class="bbai-pagination-btn bbai-pagination-btn--current">' . esc_html( $i ) . '</span>';
										} else {
											echo '<a href="' . esc_url( add_query_arg( 'alt_page', $i ) ) . '" class="bbai-pagination-btn">' . esc_html( $i ) . '</a>';
										}
									}

									if ( $end_page < $total_pages ) {
										if ( $end_page < $total_pages - 1 ) {
											echo '<span class="bbai-pagination-ellipsis">...</span>';
										}
										echo '<a href="' . esc_url( add_query_arg( 'alt_page', $total_pages ) ) . '" class="bbai-pagination-btn">' . esc_html( $total_pages ) . '</a>';
									}
									?>
								</div>
								
								<?php if ( $current_page < $total_pages ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'alt_page', $current_page + 1 ) ); ?>" class="bbai-pagination-btn bbai-pagination-btn--next" title="<?php esc_attr_e( 'Next page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
									<a href="<?php echo esc_url( add_query_arg( 'alt_page', $total_pages ) ); ?>" class="bbai-pagination-btn bbai-pagination-btn--last" title="<?php esc_attr_e( 'Last page', 'beepbeep-ai-alt-text-generator' ); ?>">
										<?php esc_html_e( 'Last', 'beepbeep-ai-alt-text-generator' ); ?>
									</a>
								<?php else : ?>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?></span>
									<span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e( 'Last', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
								</div>
					</div> <!-- End Table Card -->
					
					<!-- Upgrade Card - Full Width (hidden only for Agency, visible for Pro) -->
					<?php if ( ! $is_agency ) : ?>
					<div class="bbai-library-upgrade-card">
						<h3 class="bbai-library-upgrade-title">
							<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?>
						</h3>
						<div class="bbai-library-upgrade-features">
							<div class="bbai-library-upgrade-feature">
								<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Bulk ALT optimisation', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-library-upgrade-feature">
								<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Unlimited background queue', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-library-upgrade-feature">
								<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Smart tone tuning', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-library-upgrade-feature">
								<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Priority support', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
						</div>
						<button type="button" 
								class="bbai-library-upgrade-btn"
								data-action="show-upgrade-modal">
							<?php esc_html_e( 'View Plans', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
					</div>
					<?php endif; ?>
					</div> <!-- End Dashboard Container -->
				
				<!-- Status for AJAX operations -->
				<div class="bbai-dashboard__status" data-progress-status role="status" aria-live="polite" style="display: none;"></div>
				

			</div>

<?php elseif ( $tab === 'debug' ) : ?>
	<?php if ( ! $this->api_client->is_authenticated() ) : ?>
		<!-- Debug Logs require authentication -->
		<div class="bbai-settings-required">
			<div class="bbai-settings-required-content">
				<div class="bbai-settings-required-icon">🔒</div>
				<h2><?php esc_html_e( 'Authentication Required', 'beepbeep-ai-alt-text-generator' ); ?></h2>
				<p><?php esc_html_e( 'Debug Logs are only available to authenticated agency users. Please log in with your agency credentials to access this section.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<p style="margin-top: 12px; font-size: 14px; color: #6b7280;">
					<?php esc_html_e( 'If you don\'t have agency credentials, please contact your agency administrator or log in with the correct account.', 'beepbeep-ai-alt-text-generator' ); ?>
				</p>
				<button type="button" class="bbai-btn-primary bbai-btn-icon" data-action="show-auth-modal" data-auth-tab="login" style="margin-top: 20px;">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
						<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
						<circle cx="8" cy="8" r="2" fill="currentColor"/>
					</svg>
					<?php esc_html_e( 'Log In', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
			</div>
		</div>
	<?php elseif ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) : ?>
		<div class="bbai-section">
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Debug logging is not available on this site. Please reinstall the logging module.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
		</div>
	<?php else : ?>
		<?php
			$debug_logs       = $debug_bootstrap['logs'] ?? array();
			$debug_stats      = $debug_bootstrap['stats'] ?? array();
			$debug_pagination = $debug_bootstrap['pagination'] ?? array();
			$debug_page       = max( 1, intval( $debug_pagination['page'] ?? 1 ) );
			$debug_pages      = max( 1, intval( $debug_pagination['total_pages'] ?? 1 ) );
			$debug_export_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=bbai_debug_export' ),
				'bbai_debug_export'
			);

			// Get plan info for upgrade card
			$plan_slug = isset( $usage_stats ) && isset( $usage_stats['plan'] ) ? $usage_stats['plan'] : 'free';
			$is_pro    = ( $plan_slug === 'pro' || $plan_slug === 'agency' );

			// Add inline styles for debug panel responsive grid
			$debug_responsive_css = '
                /* Responsive Debug Stats Grid */
                @media (max-width: 768px) {
                    [data-bbai-debug-panel] .debug-stats-grid {
                        grid-template-columns: repeat(2, 1fr) !important;
                        gap: 16px !important;
                    }
                }
                @media (max-width: 480px) {
                    [data-bbai-debug-panel] .debug-stats-grid {
                        grid-template-columns: 1fr !important;
                        gap: 16px !important;
                    }
                }
            ';
			wp_add_inline_style( 'bbai-debug-styles', $debug_responsive_css );
		?>
		<div class="bbai-dashboard-container" data-bbai-debug-panel>
			<!-- Header Section -->
			<div class="bbai-debug-header">
				<h1 class="bbai-dashboard-title"><?php esc_html_e( 'Debug Logs', 'beepbeep-ai-alt-text-generator' ); ?></h1>
				<p class="bbai-dashboard-subtitle"><?php esc_html_e( 'Monitor API calls, queue events, and any errors generated by the plugin.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			
				<!-- Usage Info -->
			<?php if ( isset( $usage_stats ) ) : ?>
				<div class="bbai-debug-usage-info">
					<?php
					printf(
						esc_html__( '%1$d of %2$d image descriptions generated this month', 'beepbeep-ai-alt-text-generator' ),
						$usage_stats['used'] ?? 0,
						$usage_stats['limit'] ?? 0
					);
					?>
					<span class="bbai-debug-usage-separator">•</span>
					<?php
					$reset_date = $usage_stats['reset_date'] ?? '';
					if ( ! empty( $reset_date ) ) {
						$reset_timestamp = strtotime( $reset_date );
						if ( $reset_timestamp !== false ) {
							$formatted_reset = date_i18n( 'F j, Y', $reset_timestamp );
							printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), esc_html( $formatted_reset ) );
						} else {
							printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), esc_html( $reset_date ) );
						}
					}
					?>
			</div>
			<?php endif; ?>
			</div>

			<!-- Log Statistics Card -->
			<div class="bbai-debug-stats-card">
				<div class="bbai-debug-stats-header">
					<div class="bbai-debug-stats-title">
						<h3><?php esc_html_e( 'Log Statistics', 'beepbeep-ai-alt-text-generator' ); ?></h3>
					</div>
					<div class="bbai-debug-stats-actions">
						<button type="button" class="bbai-debug-btn bbai-debug-btn--secondary" data-debug-clear>
							<?php esc_html_e( 'Clear Logs', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
						<a href="<?php echo esc_url( $debug_export_url ); ?>" class="bbai-debug-btn bbai-debug-btn--primary">
							<?php esc_html_e( 'Export CSV', 'beepbeep-ai-alt-text-generator' ); ?>
						</a>
					</div>
				</div>
				
				<!-- Stats Grid -->
				<div class="bbai-debug-stats-grid">
					<div class="bbai-debug-stat-item">
						<div class="bbai-debug-stat-label"><?php esc_html_e( 'TOTAL LOGS', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<div class="bbai-debug-stat-value" data-debug-stat="total">
							<?php echo esc_html( number_format_i18n( intval( $debug_stats['total'] ?? 0 ) ) ); ?>
						</div>
					</div>
					<div class="bbai-debug-stat-item bbai-debug-stat-item--warning">
						<div class="bbai-debug-stat-label"><?php esc_html_e( 'WARNINGS', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<div class="bbai-debug-stat-value bbai-debug-stat-value--warning" data-debug-stat="warnings">
							<?php echo esc_html( number_format_i18n( intval( $debug_stats['warnings'] ?? 0 ) ) ); ?>
						</div>
					</div>
					<div class="bbai-debug-stat-item bbai-debug-stat-item--error">
						<div class="bbai-debug-stat-label"><?php esc_html_e( 'ERRORS', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<div class="bbai-debug-stat-value bbai-debug-stat-value--error" data-debug-stat="errors">
							<?php echo esc_html( number_format_i18n( intval( $debug_stats['errors'] ?? 0 ) ) ); ?>
						</div>
					</div>
					<div class="bbai-debug-stat-item">
						<div class="bbai-debug-stat-label"><?php esc_html_e( 'LAST API EVENT', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<div class="bbai-debug-stat-value bbai-debug-stat-value--small" data-debug-stat="last_api">
							<?php echo esc_html( $debug_stats['last_api'] ?? '—' ); ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Filters Panel -->
			<div class="bbai-debug-filters-card">
				<form data-debug-filter class="bbai-debug-filters-form">
					<div class="bbai-debug-filter-group">
						<label for="bbai-debug-level" class="bbai-debug-filter-label">
							<?php esc_html_e( 'Level', 'beepbeep-ai-alt-text-generator' ); ?>
						</label>
						<select id="bbai-debug-level" name="level" class="bbai-debug-filter-input">
							<option value=""><?php esc_html_e( 'All levels', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="info"><?php esc_html_e( 'Info', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="warning"><?php esc_html_e( 'Warning', 'beepbeep-ai-alt-text-generator' ); ?></option>
							<option value="error"><?php esc_html_e( 'Error', 'beepbeep-ai-alt-text-generator' ); ?></option>
						</select>
					</div>
					<div class="bbai-debug-filter-group">
						<label for="bbai-debug-date" class="bbai-debug-filter-label">
							<?php esc_html_e( 'Date', 'beepbeep-ai-alt-text-generator' ); ?>
						</label>
						<input type="date" id="bbai-debug-date" name="date" class="bbai-debug-filter-input" placeholder="dd/mm/yyyy">
					</div>
					<div class="bbai-debug-filter-group bbai-debug-filter-group--search">
						<label for="bbai-debug-search" class="bbai-debug-filter-label">
							<?php esc_html_e( 'Search', 'beepbeep-ai-alt-text-generator' ); ?>
						</label>
						<div class="bbai-debug-search-wrapper">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-debug-search-icon">
								<circle cx="7" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/>
								<path d="M10 10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
							<input type="search" id="bbai-debug-search" name="search" placeholder="<?php esc_attr_e( 'Search logs…', 'beepbeep-ai-alt-text-generator' ); ?>" class="bbai-debug-filter-input bbai-debug-filter-input--search">
						</div>
					</div>
					<div class="bbai-debug-filter-actions">
						<button type="submit" class="bbai-debug-btn bbai-debug-btn--primary">
							<?php esc_html_e( 'Apply', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
						<button type="button" class="bbai-debug-btn bbai-debug-btn--ghost" data-debug-reset>
							<?php esc_html_e( 'Reset', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Table Card -->
			<div class="bbai-debug-table-card">
				<table class="bbai-debug-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'TIMESTAMP', 'beepbeep-ai-alt-text-generator' ); ?></th>
							<th><?php esc_html_e( 'LEVEL', 'beepbeep-ai-alt-text-generator' ); ?></th>
							<th><?php esc_html_e( 'MESSAGE', 'beepbeep-ai-alt-text-generator' ); ?></th>
							<th><?php esc_html_e( 'CONTEXT', 'beepbeep-ai-alt-text-generator' ); ?></th>
						</tr>
					</thead>
					<tbody data-debug-rows>
						<?php if ( ! empty( $debug_logs ) ) : ?>
							<?php
							$row_index = 0;
							foreach ( $debug_logs as $log ) :
								++$row_index;
								$level          = strtolower( $log['level'] ?? 'info' );
								$level_slug     = preg_replace( '/[^a-z0-9_-]/i', '', $level ) ?: 'info';
								$context_attr   = '';
								$context_source = $log['context'] ?? array();
								if ( ! empty( $context_source ) ) {
									if ( is_array( $context_source ) ) {
										$json         = wp_json_encode( $context_source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
										$context_attr = base64_encode( $json );
									} else {
										$context_str = (string) $context_source;
										$decoded     = json_decode( $context_str, true );
										if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
											$json         = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
											$context_attr = base64_encode( $json );
										} else {
											$context_attr = base64_encode( $context_str );
										}
									}
								}

								// Format date: "Nov 13, 2025 — 12:45 PM"
								$created_at     = $log['created_at'] ?? '';
								$formatted_date = $created_at;
								if ( $created_at ) {
									$timestamp = strtotime( $created_at );
									if ( $timestamp !== false ) {
										$formatted_date = gmdate( 'M j, Y — g:i A', $timestamp );
									}
								}

								// Severity badge classes
								$badge_class = 'bbai-debug-badge bbai-debug-badge--' . esc_attr( $level_slug );
								?>
							<tr class="bbai-debug-table-row" data-row-index="<?php echo esc_attr( $row_index ); ?>">
								<td class="bbai-debug-table-cell bbai-debug-table-cell--timestamp">
									<?php echo esc_html( $formatted_date ); ?>
								</td>
								<td class="bbai-debug-table-cell bbai-debug-table-cell--level">
									<span class="<?php echo esc_attr( $badge_class ); ?>">
										<span class="bbai-debug-badge-text"><?php echo esc_html( ucfirst( $level_slug ) ); ?></span>
									</span>
								</td>
								<td class="bbai-debug-table-cell bbai-debug-table-cell--message">
									<?php echo esc_html( $log['message'] ?? '' ); ?>
								</td>
								<td class="bbai-debug-table-cell bbai-debug-table-cell--context">
									<?php if ( $context_attr ) : ?>
										<button type="button" 
												class="bbai-debug-context-btn" 
												data-context-data="<?php echo esc_attr( $context_attr ); ?>"
												data-row-index="<?php echo esc_attr( $row_index ); ?>">
											<?php esc_html_e( 'Log Context', 'beepbeep-ai-alt-text-generator' ); ?>
										</button>
									<?php else : ?>
										<span class="bbai-debug-context-empty">—</span>
									<?php endif; ?>
								</td>
							</tr>
								<?php if ( $context_attr ) : ?>
							<tr class="bbai-debug-context-row" data-row-index="<?php echo esc_attr( $row_index ); ?>" style="display: none;">
								<td colspan="4" class="bbai-debug-context-cell">
									<div class="bbai-debug-context-content">
										<pre class="bbai-debug-context-json"></pre>
									</div>
								</td>
							</tr>
							<?php endif; ?>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="4" class="bbai-debug-table-empty">
									<?php esc_html_e( 'No logs recorded yet.', 'beepbeep-ai-alt-text-generator' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			
			<!-- Pagination -->
			<?php if ( $debug_pages > 1 ) : ?>
			<div class="bbai-debug-pagination">
				<button type="button" class="bbai-debug-pagination-btn" data-debug-page="prev" <?php disabled( $debug_page <= 1 ); ?>>
					<?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
				<span class="bbai-debug-pagination-info" data-debug-page-indicator>
					<?php
						printf(
							esc_html__( 'Page %1$s of %2$s', 'beepbeep-ai-alt-text-generator' ),
							esc_html( number_format_i18n( $debug_page ) ),
							esc_html( number_format_i18n( $debug_pages ) )
						);
					?>
				</span>
				<button type="button" class="bbai-debug-pagination-btn" data-debug-page="next" <?php disabled( $debug_page >= $debug_pages ); ?>>
					<?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?>
				</button>
			</div>
			<?php endif; ?>

			<!-- Pro Upsell Section -->
			<?php if ( ! $is_pro ) : ?>
			<div class="bbai-debug-upsell-card">
				<h3 class="bbai-debug-upsell-title"><?php esc_html_e( 'Unlock Pro Debug Console', 'beepbeep-ai-alt-text-generator' ); ?></h3>
				<ul class="bbai-debug-upsell-features">
					<li class="bbai-debug-upsell-feature">
						<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
							<circle cx="8" cy="8" r="8" fill="#16a34a"/>
							<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span><?php esc_html_e( 'Long-term log retention', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li class="bbai-debug-upsell-feature">
						<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
							<circle cx="8" cy="8" r="8" fill="#16a34a"/>
							<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span><?php esc_html_e( 'Priority support', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li class="bbai-debug-upsell-feature">
						<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
							<circle cx="8" cy="8" r="8" fill="#16a34a"/>
							<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span><?php esc_html_e( 'High-speed global search', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li class="bbai-debug-upsell-feature">
						<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
							<circle cx="8" cy="8" r="8" fill="#16a34a"/>
							<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span><?php esc_html_e( 'Full CSV export of all logs', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li class="bbai-debug-upsell-feature">
						<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
							<circle cx="8" cy="8" r="8" fill="#16a34a"/>
							<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span><?php esc_html_e( 'API performance insights', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
				</ul>
				<button type="button" class="bbai-debug-upsell-btn" data-action="show-upgrade-modal">
					<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?> →
				</button>
			</div>
			<?php endif; ?>

			<div class="bbai-debug-toast" data-debug-toast hidden></div>
		</div>
		
		<!-- Context Button Handler - Rebuilt from Scratch -->
		<script>
		(function() {
			function toggleContext(button) {
				var contextData = button.getAttribute('data-context-data');
				var rowIndex = button.getAttribute('data-row-index');
				
				if (!contextData || !rowIndex) {
					console.error('Missing context data or row index');
					return;
				}
				
				// Find the parent row
				var row = button;
				while (row && row.tagName !== 'TR') {
					row = row.parentElement;
				}
				if (!row) return;
				
				// Find the context row
				var contextRow = null;
				var nextSibling = row.nextElementSibling;
				while (nextSibling) {
					if (nextSibling.classList.contains('bbai-debug-context-row') && 
						nextSibling.getAttribute('data-row-index') === rowIndex) {
						contextRow = nextSibling;
						break;
					}
					if (!nextSibling.classList.contains('bbai-debug-context-row')) {
						break;
					}
					nextSibling = nextSibling.nextElementSibling;
				}
				
				// If context row doesn't exist, create it
				if (!contextRow) {
					contextRow = document.createElement('tr');
					contextRow.className = 'bbai-debug-context-row';
					contextRow.setAttribute('data-row-index', rowIndex);
					contextRow.style.display = 'none';
					
					var cell = document.createElement('td');
					cell.className = 'bbai-debug-context-cell';
					cell.colSpan = 4;
					
					var content = document.createElement('div');
					content.className = 'bbai-debug-context-content';
					
					var pre = document.createElement('pre');
					pre.className = 'bbai-debug-context-json';
					
					content.appendChild(pre);
					cell.appendChild(content);
					contextRow.appendChild(cell);
					
					// Insert after the current row
					if (row.nextSibling) {
						row.parentNode.insertBefore(contextRow, row.nextSibling);
					} else {
						row.parentNode.appendChild(contextRow);
					}
				}
				
				var preElement = contextRow.querySelector('pre.bbai-debug-context-json');
				if (!preElement) return;
				
				var isVisible = contextRow.style.display !== 'none';
				
				if (isVisible) {
					// Hide
					contextRow.style.display = 'none';
					button.textContent = 'Log Context';
					button.classList.remove('is-expanded');
				} else {
					// Show - decode and display
					var decoded = null;
					
					// Try to decode base64
					try {
						if (/^[A-Za-z0-9+\/]*={0,2}$/.test(contextData)) {
							var decodedStr = decodeURIComponent(escape(atob(contextData)));
							decoded = JSON.parse(decodedStr);
						}
					} catch(e1) {
						// Try direct JSON parse
						try {
							decoded = JSON.parse(contextData);
						} catch(e2) {
							// Try URL decode then parse
							try {
								decoded = JSON.parse(decodeURIComponent(contextData));
							} catch(e3) {
								decoded = { error: 'Unable to decode context data' };
							}
						}
					}
					
					var output = JSON.stringify(decoded, null, 2);
					preElement.textContent = output;
					contextRow.style.display = 'table-row';
					button.textContent = 'Hide Context';
					button.classList.add('is-expanded');
				}
			}
			
			// Bind to all context buttons
			document.addEventListener('click', function(e) {
				var btn = e.target;
				while (btn && btn !== document.body) {
					if (btn.classList && btn.classList.contains('bbai-debug-context-btn')) {
						e.preventDefault();
						e.stopPropagation();
						toggleContext(btn);
						return;
					}
					btn = btn.parentElement;
				}
			});
		})();
		</script>
	<?php endif; ?>

<?php elseif ( $tab === 'guide' ) : ?>
			<!-- How to Use Page -->
			<?php
			// Get plan info for upgrade card - check license first
			$has_license  = $this->api_client->has_active_license();
			$license_data = $this->api_client->get_license_data();
			$plan_slug    = isset( $usage_stats ) && isset( $usage_stats['plan'] ) ? $usage_stats['plan'] : 'free';

			// If using license, check license plan
			if ( $has_license && $license_data && isset( $license_data['organization'] ) ) {
				$license_plan = strtolower( $license_data['organization']['plan'] ?? 'free' );
				if ( $license_plan !== 'free' ) {
					$plan_slug = $license_plan;
				}
			}

			$is_pro    = ( $plan_slug === 'pro' || $plan_slug === 'agency' );
			$is_agency = ( $plan_slug === 'agency' );
			?>
			<div class="bbai-guide-container">
				<!-- Header Section -->
				<div class="bbai-guide-header">
					<h1 class="bbai-guide-title"><?php esc_html_e( 'How to Use BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?></h1>
					<p class="bbai-guide-subtitle"><?php esc_html_e( 'Learn how to generate and manage alt text for your images.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				</div>

				<!-- Pro Features Card (LOCKED) -->
				<?php if ( ! $is_pro ) : ?>
				<div class="bbai-guide-pro-card">
					<div class="bbai-guide-pro-ribbon">
						<?php esc_html_e( 'LOCKED PRO FEATURES', 'beepbeep-ai-alt-text-generator' ); ?>
					</div>
					<div class="bbai-guide-pro-features">
						<div class="bbai-guide-pro-feature">
							<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
								<circle cx="8" cy="8" r="8" fill="#16a34a"/>
								<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Priority queue generation', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
						<div class="bbai-guide-pro-feature">
							<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
								<circle cx="8" cy="8" r="8" fill="#16a34a"/>
								<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Bulk optimisation for large libraries', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
						<div class="bbai-guide-pro-feature">
							<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
								<circle cx="8" cy="8" r="8" fill="#16a34a"/>
								<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Multilingual alt text', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
						<div class="bbai-guide-pro-feature">
							<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
								<circle cx="8" cy="8" r="8" fill="#16a34a"/>
								<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Smart tone + style tuning', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
						<div class="bbai-guide-pro-feature">
							<svg width="18" height="18" viewBox="0 0 16 16" fill="none">
								<circle cx="8" cy="8" r="8" fill="#16a34a"/>
								<path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( '1,000 alt text generations per month', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</div>
					</div>
					<div class="bbai-guide-pro-cta">
						<a href="#" class="bbai-guide-pro-link" data-action="show-upgrade-modal">
							<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?> →
						</a>
					</div>
				</div>
				<?php endif; ?>

				<!-- Getting Started Card -->
				<div class="bbai-guide-steps-card">
					<h2 class="bbai-guide-steps-title">
						<?php esc_html_e( 'Getting Started in 4 Easy Steps', 'beepbeep-ai-alt-text-generator' ); ?>
					</h2>
					<div class="bbai-guide-steps-list">
						<div class="bbai-guide-step">
							<div class="bbai-guide-step-badge">
								<span class="bbai-guide-step-number">1</span>
							</div>
							<div class="bbai-guide-step-content">
								<h3 class="bbai-guide-step-title"><?php esc_html_e( 'Upload Images', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								<p class="bbai-guide-step-description"><?php esc_html_e( 'Add images to your WordPress Media Library.', 'beepbeep-ai-alt-text-generator' ); ?></p>
							</div>
						</div>
						<div class="bbai-guide-step">
							<div class="bbai-guide-step-badge">
								<span class="bbai-guide-step-number">2</span>
							</div>
							<div class="bbai-guide-step-content">
								<h3 class="bbai-guide-step-title"><?php esc_html_e( 'Bulk Optimize', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								<p class="bbai-guide-step-description"><?php esc_html_e( 'Generate alt text for multiple images at once from the Dashboard.', 'beepbeep-ai-alt-text-generator' ); ?></p>
							</div>
						</div>
						<div class="bbai-guide-step">
							<div class="bbai-guide-step-badge">
								<span class="bbai-guide-step-number">3</span>
							</div>
							<div class="bbai-guide-step-content">
								<h3 class="bbai-guide-step-title"><?php esc_html_e( 'Review & Edit', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								<p class="bbai-guide-step-description"><?php esc_html_e( 'Refine generated alt text in the ALT Library.', 'beepbeep-ai-alt-text-generator' ); ?></p>
							</div>
						</div>
						<div class="bbai-guide-step">
							<div class="bbai-guide-step-badge">
								<span class="bbai-guide-step-number">4</span>
							</div>
							<div class="bbai-guide-step-content">
								<h3 class="bbai-guide-step-title"><?php esc_html_e( 'Regenerate if Needed', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								<p class="bbai-guide-step-description"><?php esc_html_e( 'Use the regenerate feature to improve alt text quality anytime.', 'beepbeep-ai-alt-text-generator' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- Why Alt Text Matters Section -->
				<div class="bbai-guide-why-card">
					<div class="bbai-guide-why-icon">💡</div>
					<h3 class="bbai-guide-why-title">
						<?php esc_html_e( 'Why Alt Text Matters', 'beepbeep-ai-alt-text-generator' ); ?>
					</h3>
					<ul class="bbai-guide-why-list">
						<li class="bbai-guide-why-item">
							<span class="bbai-guide-why-check">✓</span>
							<span><?php esc_html_e( 'Boosts SEO visibility by up to 20%', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</li>
						<li class="bbai-guide-why-item">
							<span class="bbai-guide-why-check">✓</span>
							<span><?php esc_html_e( 'Improves Google Images ranking', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</li>
						<li class="bbai-guide-why-item">
							<span class="bbai-guide-why-check">✓</span>
							<span><?php esc_html_e( 'Helps achieve WCAG compliance for accessibility', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</li>
					</ul>
				</div>

				<!-- Two Column Layout -->
				<div class="bbai-guide-grid">
					<!-- Tips Card -->
					<div class="bbai-guide-card">
						<h3 class="bbai-guide-card-title">
							<?php esc_html_e( 'Tips for Better Alt Text', 'beepbeep-ai-alt-text-generator' ); ?>
						</h3>
						<div class="bbai-guide-tips-list">
							<div class="bbai-guide-tip">
								<div class="bbai-guide-tip-icon">✓</div>
								<div class="bbai-guide-tip-content">
									<div class="bbai-guide-tip-title"><?php esc_html_e( 'Keep it concise', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
							<div class="bbai-guide-tip">
								<div class="bbai-guide-tip-icon">✓</div>
								<div class="bbai-guide-tip-content">
									<div class="bbai-guide-tip-title"><?php esc_html_e( 'Be specific', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
							<div class="bbai-guide-tip">
								<div class="bbai-guide-tip-icon">✓</div>
								<div class="bbai-guide-tip-content">
									<div class="bbai-guide-tip-title"><?php esc_html_e( 'Avoid redundancy', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
							<div class="bbai-guide-tip">
								<div class="bbai-guide-tip-icon">✓</div>
								<div class="bbai-guide-tip-content">
									<div class="bbai-guide-tip-title"><?php esc_html_e( 'Think context', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Features Card -->
					<div class="bbai-guide-card">
						<h3 class="bbai-guide-card-title">
							<?php esc_html_e( 'Key Features', 'beepbeep-ai-alt-text-generator' ); ?>
						</h3>
						<div class="bbai-guide-features-list">
							<div class="bbai-guide-feature">
								<div class="bbai-guide-feature-icon">🤖</div>
								<div class="bbai-guide-feature-content">
									<div class="bbai-guide-feature-title"><?php esc_html_e( 'AI-Powered', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
							<div class="bbai-guide-feature">
								<div class="bbai-guide-feature-icon">≡</div>
								<div class="bbai-guide-feature-content">
									<div class="bbai-guide-feature-title">
										<?php esc_html_e( 'Bulk Processing', 'beepbeep-ai-alt-text-generator' ); ?>
										<?php if ( ! $is_pro ) : ?>
											<span class="bbai-guide-feature-lock">🔒</span>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<div class="bbai-guide-feature">
								<div class="bbai-guide-feature-icon">◆</div>
								<div class="bbai-guide-feature-content">
									<div class="bbai-guide-feature-title"><?php esc_html_e( 'SEO Optimized', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
							<div class="bbai-guide-feature">
								<div class="bbai-guide-feature-icon">🎨</div>
								<div class="bbai-guide-feature-content">
									<div class="bbai-guide-feature-title">
										<?php esc_html_e( 'Smart tone tuning', 'beepbeep-ai-alt-text-generator' ); ?>
										<?php if ( ! $is_pro ) : ?>
											<span class="bbai-guide-feature-lock">🔒</span>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<div class="bbai-guide-feature">
								<div class="bbai-guide-feature-icon">🌍</div>
								<div class="bbai-guide-feature-content">
									<div class="bbai-guide-feature-title">
										<?php esc_html_e( 'Multilingual alt text', 'beepbeep-ai-alt-text-generator' ); ?>
										<?php if ( ! $is_pro ) : ?>
											<span class="bbai-guide-feature-lock">🔒</span>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<div class="bbai-guide-feature">
								<div class="bbai-guide-feature-icon">♿</div>
								<div class="bbai-guide-feature-content">
									<div class="bbai-guide-feature-title"><?php esc_html_e( 'Accessibility', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Upgrade CTA Banner -->
				<?php if ( ! $is_agency ) : ?>
				<div class="bbai-guide-cta-card">
					<h3 class="bbai-guide-cta-title">
						<span class="bbai-guide-cta-icon">⚡</span>
						<?php esc_html_e( 'Ready for More?', 'beepbeep-ai-alt-text-generator' ); ?>
					</h3>
					<p class="bbai-guide-cta-text">
						<?php esc_html_e( 'Save hours each month with automated alt text generation. Upgrade for 1,000 images/month and priority processing.', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
					<button type="button" class="bbai-guide-cta-btn" data-action="show-upgrade-modal">
						<span><?php esc_html_e( 'View Plans & Pricing', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
							<path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span class="bbai-guide-cta-badge-new"><?php esc_html_e( 'NEW', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</button>
				</div>
				<?php endif; ?>
			</div>


	<?php
elseif ( $tab === 'credit-usage' && ( $is_authenticated || $has_license ) ) :
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-credit-usage-page.php';
				\BeepBeepAI\AltTextGenerator\Credit_Usage_Page::render_page_content();
	?>
<?php elseif ( $tab === 'settings' ) : ?>
			<?php if ( ! $this->api_client->is_authenticated() ) : ?>
				<!-- Settings require authentication -->
				<div class="bbai-settings-required">
					<div class="bbai-settings-required-content">
						<div class="bbai-settings-required-icon">🔒</div>
						<h2><?php esc_html_e( 'Authentication Required', 'beepbeep-ai-alt-text-generator' ); ?></h2>
						<p><?php esc_html_e( 'Settings are only available to authenticated agency users. Please log in with your agency credentials to access this section.', 'beepbeep-ai-alt-text-generator' ); ?></p>
						<p style="margin-top: 12px; font-size: 14px; color: #6b7280;">
							<?php esc_html_e( 'If you don\'t have agency credentials, please contact your agency administrator or log in with the correct account.', 'beepbeep-ai-alt-text-generator' ); ?>
						</p>
						<button type="button" class="bbai-btn-primary bbai-btn-icon" data-action="show-auth-modal" data-auth-tab="login" style="margin-top: 20px;">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
								<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
								<circle cx="8" cy="8" r="2" fill="currentColor"/>
							</svg>
							<span><?php esc_html_e( 'Log In', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</button>
					</div>
				</div>
			<?php else : ?>
			<!-- Settings Page -->
			<div class="bbai-settings-page">
				<?php
				// Pull fresh usage from backend to avoid stale cache in Settings
				if ( isset( $this->api_client ) ) {
					$live = $this->api_client->get_usage();
					if ( is_array( $live ) && ! empty( $live ) ) {
						Usage_Tracker::update_usage( $live ); }
				}
				$usage_box = Usage_Tracker::get_stats_display();
				$o         = wp_parse_args( $opts, array() );

				// Check for license plan first
				$has_license  = $this->api_client->has_active_license();
				$license_data = $this->api_client->get_license_data();

				$plan = $usage_box['plan'] ?? 'free';

				// If license is active, use license plan
				if ( $has_license && $license_data && isset( $license_data['organization'] ) ) {
					$license_plan = strtolower( $license_data['organization']['plan'] ?? 'free' );
					if ( $license_plan !== 'free' ) {
						$plan = $license_plan;
					}
				}

				$is_pro        = $plan === 'pro';
				$is_agency     = $plan === 'agency';
				$usage_percent = $usage_box['limit'] > 0 ? ( $usage_box['used'] / $usage_box['limit'] * 100 ) : 0;

				// Determine plan badge text
				if ( $is_agency ) {
					$plan_badge_text = esc_html__( 'AGENCY', 'beepbeep-ai-alt-text-generator' );
				} elseif ( $is_pro ) {
					$plan_badge_text = esc_html__( 'PRO', 'beepbeep-ai-alt-text-generator' );
				} else {
					$plan_badge_text = esc_html__( 'FREE', 'beepbeep-ai-alt-text-generator' );
				}
				?>

				<!-- Header Section -->
				<div class="bbai-settings-page-header">
					<h1 class="bbai-settings-page-title"><?php esc_html_e( 'Settings & Account', 'beepbeep-ai-alt-text-generator' ); ?></h1>
					<p class="bbai-settings-page-subtitle"><?php esc_html_e( 'Configure automatic alt text generation, manage your monthly quota, and track usage. Optimize settings to maximize Google Images rankings.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				</div>
				
				<!-- Site-Wide Settings Banner -->
				<div class="bbai-settings-sitewide-banner">
					<svg class="bbai-settings-sitewide-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
						<circle cx="10" cy="10" r="8" stroke="#3b82f6" stroke-width="2" fill="none"/>
						<path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
					</svg>
					<div class="bbai-settings-sitewide-content">
						<strong class="bbai-settings-sitewide-title"><?php esc_html_e( 'Site-Wide Settings', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<span class="bbai-settings-sitewide-text">
							<?php esc_html_e( 'These settings apply to all users on this WordPress site.', 'beepbeep-ai-alt-text-generator' ); ?>
						</span>
					</div>
				</div>

				<!-- Plan Summary Card -->
				<div class="bbai-settings-plan-summary-card">
					<div class="bbai-settings-plan-badge-top">
						<span class="bbai-settings-plan-badge-text"><?php echo esc_html( $plan_badge_text ); ?></span>
					</div>
					<div class="bbai-settings-plan-quota">
						<div class="bbai-settings-plan-quota-meter">
							<span class="bbai-settings-plan-quota-used"><?php echo esc_html( $usage_box['used'] ); ?></span>
							<span class="bbai-settings-plan-quota-divider">/</span>
							<span class="bbai-settings-plan-quota-limit"><?php echo esc_html( $usage_box['limit'] ); ?></span>
						</div>
						<div class="bbai-settings-plan-quota-label">
							<?php esc_html_e( 'image descriptions', 'beepbeep-ai-alt-text-generator' ); ?>
					</div>
					</div>
					<div class="bbai-settings-plan-info">
						<?php if ( ! $is_pro && ! $is_agency ) : ?>
							<div class="bbai-settings-plan-info-item">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
									<path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
								</svg>
								<span>
									<?php
									if ( isset( $usage_box['reset_date'] ) ) {
										printf(
											esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ),
											'<strong>' . esc_html( $usage_box['reset_date'] ) . '</strong>'
										);
									} else {
										esc_html_e( 'Monthly quota', 'beepbeep-ai-alt-text-generator' );
									}
									?>
								</span>
							</div>
							<div class="bbai-settings-plan-info-item">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
									<circle cx="8" cy="8" r="2" fill="currentColor"/>
								</svg>
								<span><?php esc_html_e( 'Shared across all users', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
						<?php elseif ( $is_agency ) : ?>
							<div class="bbai-settings-plan-info-item">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Multi-site license', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-settings-plan-info-item">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), '<strong>' . esc_html( $usage_box['reset_date'] ?? 'Monthly' ) . '</strong>' ); ?></span>
							</div>
						<?php else : ?>
							<div class="bbai-settings-plan-info-item">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( '1,000 generations per month', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
							<div class="bbai-settings-plan-info-item">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Priority support', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<?php if ( ! $is_pro && ! $is_agency ) : ?>
					<button type="button" class="bbai-settings-plan-upgrade-btn-large" data-action="show-upgrade-modal">
						<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?>
					</button>
					<?php endif; ?>
				</div>

				<!-- License Management Card -->
				<?php
				// Reuse license variables if already set above
				if ( ! isset( $has_license ) ) {
					$has_license  = $this->api_client->has_active_license();
					$license_data = $this->api_client->get_license_data();
				}

				// Auto-attach license for authenticated free users who don't have one yet
				$is_authenticated = $this->api_client->is_authenticated();
				if ( $is_authenticated && ! $has_license ) {
					// Check if user is on free plan and doesn't have license
					$usage = $this->api_client->get_usage();
					if ( ! is_wp_error( $usage ) && isset( $usage['plan'] ) && $usage['plan'] === 'free' ) {
						// Try to auto-attach free license
						$auto_attach_result = $this->api_client->auto_attach_license();
						if ( ! is_wp_error( $auto_attach_result ) && isset( $auto_attach_result['success'] ) && $auto_attach_result['success'] ) {
							// Refresh license status
							$has_license  = $this->api_client->has_active_license();
							$license_data = $this->api_client->get_license_data();
						}
					}
				}

				$diagnostic_site_hash = '';
				if ( method_exists( $this->api_client, 'get_site_id' ) ) {
					$diagnostic_site_hash = sanitize_text_field( $this->api_client->get_site_id() );
				}
				$diagnostic_site_url = get_site_url();
				?>

				<div class="bbai-settings-card">
					<div class="bbai-settings-card-header">
						<div class="bbai-settings-card-header-icon">
							<span class="bbai-settings-card-icon-emoji">🔑</span>
						</div>
						<h3 class="bbai-settings-card-title"><?php esc_html_e( 'License', 'beepbeep-ai-alt-text-generator' ); ?></h3>
					</div>

					<?php if ( $has_license && $license_data ) : ?>
						<?php
						$org = $license_data['organization'] ?? null;
						if ( $org ) :
							?>
						<!-- Active License Display -->
						<div class="bbai-settings-license-active">
							<div class="bbai-settings-license-status">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
									<circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
									<path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<div>
									<div class="bbai-settings-license-title"><?php esc_html_e( 'License Active', 'beepbeep-ai-alt-text-generator' ); ?></div>
									<div class="bbai-settings-license-subtitle"><?php echo esc_html( $org['name'] ?? '' ); ?></div>
									<?php
									// Display license key for Pro and Agency users
									$license_key = $this->api_client->get_license_key();
									if ( ! empty( $license_key ) ) :
										$license_plan = strtolower( $org['plan'] ?? 'free' );
										if ( $license_plan === 'pro' || $license_plan === 'agency' ) :
											?>
									<div class="bbai-settings-license-key" style="margin-top: 8px; font-size: 12px; color: #6b7280; font-family: monospace; word-break: break-all;">
										<strong><?php esc_html_e( 'License Key:', 'beepbeep-ai-alt-text-generator' ); ?></strong> <?php echo esc_html( $license_key ); ?>
									</div>
											<?php
										endif;
									endif;
									?>
								</div>
							</div>
							<button type="button" class="bbai-settings-license-deactivate-btn" data-action="deactivate-license">
								<?php esc_html_e( 'Deactivate', 'beepbeep-ai-alt-text-generator' ); ?>
							</button>
						</div>
						
							<?php
							// Show site usage for agency licenses (can use license key or JWT auth)
							$is_authenticated  = $this->api_client->is_authenticated();
							$has_license       = $this->api_client->has_active_license();
							$is_agency_license = isset( $license_data['organization']['plan'] ) && $license_data['organization']['plan'] === 'agency';

							// Show for agency licenses with either JWT auth or license key
							if ( $is_agency_license && ( $is_authenticated || $has_license ) ) :
								?>
						<!-- License Site Usage Section -->
						<div class="bbai-settings-license-sites" id="bbai-license-sites">
							<div class="bbai-settings-license-sites-header">
								<h3 class="bbai-settings-license-sites-title">
									<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
										<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
										<circle cx="8" cy="8" r="2" fill="currentColor"/>
									</svg>
									<?php esc_html_e( 'Sites Using This License', 'beepbeep-ai-alt-text-generator' ); ?>
								</h3>
							</div>
							<div class="bbai-settings-license-sites-content" id="bbai-license-sites-content">
								<div class="bbai-settings-license-sites-loading">
									<span class="bbai-spinner"></span>
									<?php esc_html_e( 'Loading site usage...', 'beepbeep-ai-alt-text-generator' ); ?>
								</div>
							</div>
						</div>
						<?php endif; ?>
						
							<?php
							$auto_status        = isset( $org['autoAttachStatus'] ) ? sanitize_text_field( $org['autoAttachStatus'] ) : '';
							$email_sent_at      = isset( $org['licenseEmailSentAt'] ) ? sanitize_text_field( $org['licenseEmailSentAt'] ) : '';
							$email_sent_display = '';
							if ( $email_sent_at ) {
								$sent_timestamp = strtotime( $email_sent_at );
								if ( $sent_timestamp ) {
									$email_sent_display = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sent_timestamp );
								} else {
									$email_sent_display = $email_sent_at;
								}
							}
							if ( $auto_status || $email_sent_at ) :
								?>
						<div class="bbai-settings-license-diagnostics">
							<div class="bbai-settings-license-diagnostics-row">
								<span class="bbai-settings-license-diagnostics-label"><?php esc_html_e( 'Auto-attach status', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<span class="bbai-settings-license-diagnostics-value">
									<?php echo esc_html( $auto_status ?: __( 'Not available', 'beepbeep-ai-alt-text-generator' ) ); ?>
								</span>
							</div>
								<?php if ( $email_sent_display ) : ?>
							<div class="bbai-settings-license-diagnostics-row">
								<span class="bbai-settings-license-diagnostics-label"><?php esc_html_e( 'License email sent', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<span class="bbai-settings-license-diagnostics-value">
									<?php echo esc_html( $email_sent_display ); ?>
								</span>
							</div>
							<?php endif; ?>
							<p class="bbai-settings-license-note">
								<?php esc_html_e( 'Need the license email resent? Contact support and we\'ll send it again.', 'beepbeep-ai-alt-text-generator' ); ?>
							</p>
						</div>
						<?php endif; ?>
						
						<?php endif; ?>
					<?php else : ?>
						<!-- License Activation Form -->
						<div class="bbai-settings-license-form">
							<p class="bbai-settings-license-description">
								<?php esc_html_e( 'Enter your license key to activate this site. Agency licenses can be used across multiple sites.', 'beepbeep-ai-alt-text-generator' ); ?>
							</p>
							<form id="license-activation-form">
								<div class="bbai-settings-license-input-group">
									<label for="license-key-input" class="bbai-settings-license-label">
										<?php esc_html_e( 'License Key', 'beepbeep-ai-alt-text-generator' ); ?>
									</label>
									<input type="text"
											id="license-key-input"
											name="license_key"
											class="bbai-settings-license-input"
											placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
											pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
											required>
								</div>
								<div id="license-activation-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
								<button type="submit" id="activate-license-btn" class="bbai-settings-license-activate-btn">
									<?php esc_html_e( 'Activate License', 'beepbeep-ai-alt-text-generator' ); ?>
								</button>
							</form>
						</div>
					<?php endif; ?>
				</div>

				<!-- Hidden nonce for AJAX requests -->
				<input type="hidden" id="license-nonce" value="<?php echo esc_attr( wp_create_nonce( 'beepbeepai_nonce' ) ); ?>">

				<!-- Account Management Card -->
				<div class="bbai-settings-card">
					<div class="bbai-settings-card-header">
						<div class="bbai-settings-card-header-icon">
							<span class="bbai-settings-card-icon-emoji">👤</span>
						</div>
						<h3 class="bbai-settings-card-title"><?php esc_html_e( 'Account Management', 'beepbeep-ai-alt-text-generator' ); ?></h3>
					</div>
					
					<?php if ( ! $is_pro && ! $is_agency ) : ?>
					<div class="bbai-settings-account-info-banner">
						<span><?php esc_html_e( 'You are on the free plan.', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</div>
					<div class="bbai-settings-account-upgrade-link">
						<button type="button" class="bbai-settings-account-upgrade-btn" data-action="show-upgrade-modal">
							<?php esc_html_e( 'Upgrade Now', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
					</div>
					<?php else : ?>
					<div class="bbai-settings-account-status">
						<span class="bbai-settings-account-status-label"><?php esc_html_e( 'Current Plan:', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<span class="bbai-settings-account-status-value">
						<?php
						if ( $is_agency ) {
							esc_html_e( 'Agency', 'beepbeep-ai-alt-text-generator' );
						} else {
							esc_html_e( 'Pro', 'beepbeep-ai-alt-text-generator' );
						}
						?>
						</span>
					</div>
						<?php
						// Check if using license vs authenticated account
						$is_authenticated_for_account = $this->api_client->is_authenticated();
						$is_license_only              = $has_license && ! $is_authenticated_for_account;

						if ( $is_license_only ) :
							// License-based plan - provide contact info
							?>
					<div class="bbai-settings-account-actions">
						<div class="bbai-settings-account-action-info">
							<p><strong><?php esc_html_e( 'License-Based Plan', 'beepbeep-ai-alt-text-generator' ); ?></strong></p>
							<p><?php esc_html_e( 'Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'beepbeep-ai-alt-text-generator' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Contact your license administrator', 'beepbeep-ai-alt-text-generator' ); ?></li>
								<li><?php esc_html_e( 'Email support for billing inquiries', 'beepbeep-ai-alt-text-generator' ); ?></li>
								<li><?php esc_html_e( 'View license details in the License section above', 'beepbeep-ai-alt-text-generator' ); ?></li>
							</ul>
						</div>
					</div>
							<?php
					elseif ( $is_authenticated_for_account ) :
						// Authenticated user - show Stripe portal
						?>
					<div class="bbai-settings-account-actions">
						<button type="button" class="bbai-settings-account-action-btn" data-action="manage-subscription">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
								<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
								<circle cx="8" cy="8" r="2" fill="currentColor"/>
							</svg>
							<span><?php esc_html_e( 'Manage Subscription', 'beepbeep-ai-alt-text-generator' ); ?></span>
						</button>
						<div class="bbai-settings-account-action-info">
							<p><?php esc_html_e( 'In Stripe Customer Portal you can:', 'beepbeep-ai-alt-text-generator' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'View and download invoices', 'beepbeep-ai-alt-text-generator' ); ?></li>
								<li><?php esc_html_e( 'Update payment method', 'beepbeep-ai-alt-text-generator' ); ?></li>
								<li><?php esc_html_e( 'View payment history', 'beepbeep-ai-alt-text-generator' ); ?></li>
								<li><?php esc_html_e( 'Cancel or modify subscription', 'beepbeep-ai-alt-text-generator' ); ?></li>
							</ul>
						</div>
					</div>
					<?php endif; ?>
					<?php endif; ?>
				</div>

				<!-- Settings Form -->
				<form method="post" action="options.php" autocomplete="off">
					<?php settings_fields( 'bbai_group' ); ?>

					<!-- Generation Settings Card -->
					<div class="bbai-settings-card">
						<h3 class="bbai-settings-generation-title"><?php esc_html_e( 'Generation Settings', 'beepbeep-ai-alt-text-generator' ); ?></h3>

						<div class="bbai-settings-form-group">
							<div class="bbai-settings-form-field bbai-settings-form-field--toggle">
								<div class="bbai-settings-form-field-content">
									<label for="bbai-enable-on-upload" class="bbai-settings-form-label">
										<?php esc_html_e( 'Auto-generate on Image Upload', 'beepbeep-ai-alt-text-generator' ); ?>
									</label>
									<p class="bbai-settings-form-description">
										<?php esc_html_e( 'Automatically generate alt text when new images are uploaded to your media library.', 'beepbeep-ai-alt-text-generator' ); ?>
									</p>
								</div>
								<label class="bbai-settings-toggle">
									<input 
										type="checkbox" 
										id="bbai-enable-on-upload"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_on_upload]" 
										value="1"
										<?php checked( ! empty( $o['enable_on_upload'] ?? true ) ); ?>
									>
									<span class="bbai-settings-toggle-slider"></span>
								</label>
							</div>
						</div>

						<div class="bbai-settings-form-group">
							<label for="bbai-tone" class="bbai-settings-form-label">
								<?php esc_html_e( 'Tone & Style', 'beepbeep-ai-alt-text-generator' ); ?>
							</label>
							<input
								type="text"
								id="bbai-tone"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[tone]"
								value="<?php echo esc_attr( $o['tone'] ?? 'professional, accessible' ); ?>"
								placeholder="<?php esc_attr_e( 'professional, accessible', 'beepbeep-ai-alt-text-generator' ); ?>"
								class="bbai-settings-form-input"
							/>
						</div>

						<div class="bbai-settings-form-group">
							<label for="bbai-custom-prompt" class="bbai-settings-form-label">
								<?php esc_html_e( 'Additional Instructions', 'beepbeep-ai-alt-text-generator' ); ?>
							</label>
							<textarea
								id="bbai-custom-prompt"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_prompt]"
								rows="4"
								placeholder="<?php esc_attr_e( 'Enter any specific instructions for the AI...', 'beepbeep-ai-alt-text-generator' ); ?>"
								class="bbai-settings-form-textarea"
							><?php echo esc_textarea( $o['custom_prompt'] ?? '' ); ?></textarea>
						</div>

						<div class="bbai-settings-form-actions">
							<button type="submit" class="bbai-settings-save-btn">
								<?php esc_html_e( 'Save Settings', 'beepbeep-ai-alt-text-generator' ); ?>
							</button>
						</div>
					</div>
				</form>

				<script>
				(function($) {
					'use strict';
					// Toggle is handled by CSS, no JavaScript needed for visual updates
				})(jQuery);
				</script>

				<!-- Pro Upsell Banner -->
					<?php if ( ! $is_agency ) : ?>
				<div class="bbai-settings-pro-upsell-banner">
					<div class="bbai-settings-pro-upsell-content">
						<h3 class="bbai-settings-pro-upsell-title">
							<?php esc_html_e( 'Want 1,000 monthly AI generations and faster processing?', 'beepbeep-ai-alt-text-generator' ); ?>
						</h3>
						<ul class="bbai-settings-pro-upsell-features">
							<li>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( '1,000 monthly AI generations', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</li>
							<li>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Priority queue', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</li>
							<li>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php esc_html_e( 'Large library batch mode', 'beepbeep-ai-alt-text-generator' ); ?></span>
							</li>
						</ul>
					</div>
					<button type="button" class="bbai-settings-pro-upsell-btn" data-action="show-upgrade-modal">
						<?php esc_html_e( 'View Plans & Pricing', 'beepbeep-ai-alt-text-generator' ); ?> →
					</button>
				</div>
				<?php endif; ?>
			</div>
			<?php endif; // End if/else for authentication check in settings tab ?>
			<?php elseif ( $tab === 'admin' && $is_pro_for_admin ) : ?>
			<!-- Admin Tab - Debug Logs and Settings for Pro and Agency -->
				<?php
				$admin_authenticated = $this->is_admin_authenticated();
				?>
				<?php if ( ! $admin_authenticated ) : ?>
				<!-- Admin Login Required -->
				<div class="bbai-admin-login">
					<div class="bbai-admin-login-content">
						<div class="bbai-admin-login-header">
							<h2 class="bbai-admin-login-title">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="margin-right: 12px; vertical-align: middle;">
									<path d="M12 1L23 12L12 23L1 12L12 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
									<circle cx="12" cy="12" r="3" fill="currentColor"/>
								</svg>
								<?php esc_html_e( 'Admin Access', 'beepbeep-ai-alt-text-generator' ); ?>
							</h2>
							<p class="bbai-admin-login-subtitle">
								<?php
								if ( $is_agency_for_admin ) {
									esc_html_e( 'Enter your agency credentials to access Debug Logs and Settings.', 'beepbeep-ai-alt-text-generator' );
								} else {
									esc_html_e( 'Enter your pro credentials to access Debug Logs and Settings.', 'beepbeep-ai-alt-text-generator' );
								}
								?>
							</p>
						</div>
						
						<form id="bbai-admin-login-form" class="bbai-admin-login-form">
							<div id="bbai-admin-login-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
							
							<div class="bbai-admin-login-field">
								<label for="admin-login-email" class="bbai-admin-login-label">
									<?php esc_html_e( 'Email', 'beepbeep-ai-alt-text-generator' ); ?>
								</label>
								<input type="email" 
										id="admin-login-email" 
										name="email" 
										class="bbai-admin-login-input" 
										placeholder="<?php esc_attr_e( 'your-email@example.com', 'beepbeep-ai-alt-text-generator' ); ?>"
										required>
							</div>
							
							<div class="bbai-admin-login-field">
								<label for="admin-login-password" class="bbai-admin-login-label">
									<?php esc_html_e( 'Password', 'beepbeep-ai-alt-text-generator' ); ?>
								</label>
								<input type="password" 
										id="admin-login-password" 
										name="password" 
										class="bbai-admin-login-input" 
										placeholder="<?php esc_attr_e( 'Enter your password', 'beepbeep-ai-alt-text-generator' ); ?>"
										required>
							</div>
							
							<button type="submit" id="admin-login-submit-btn" class="bbai-admin-login-btn">
								<span class="bbai-btn__text"><?php esc_html_e( 'Log In', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<span class="bbai-btn__spinner" style="display: none;">
									<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
										<circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="2" stroke-dasharray="43.98" stroke-dashoffset="10.99" fill="none" opacity="0.5">
											<animateTransform attributeName="transform" type="rotate" from="0 8 8" to="360 8 8" dur="1s" repeatCount="indefinite"/>
										</circle>
									</svg>
								</span>
							</button>
						</form>
					</div>
				</div>
				
				<script>
				(function($) {
					'use strict';
					
					$('#bbai-admin-login-form').on('submit', function(e) {
						e.preventDefault();
						
						const $form = $(this);
						const $status = $('#bbai-admin-login-status');
						const $btn = $('#admin-login-submit-btn');
						const $btnText = $btn.find('.bbai-btn__text');
						const $btnSpinner = $btn.find('.bbai-btn__spinner');
						
						const email = $('#admin-login-email').val().trim();
						const password = $('#admin-login-password').val();
						
						// Show loading
						$btn.prop('disabled', true);
						$btnText.hide();
						$btnSpinner.show();
						$status.hide();
						
						$.ajax({
							url: window.bbai_ajax.ajaxurl,
							type: 'POST',
							data: {
								action: 'bbai_admin_login',
								nonce: window.bbai_ajax.nonce,
								email: email,
								password: password
							},
							success: function(response) {
								if (response.success) {
									$status.removeClass('error').addClass('success').text(response.data.message || 'Successfully logged in').show();
									setTimeout(function() {
										window.location.href = response.data.redirect || window.location.href;
									}, 1000);
								} else {
									$status.removeClass('success').addClass('error').text(response.data?.message || 'Login failed').show();
									$btn.prop('disabled', false);
									$btnText.show();
									$btnSpinner.hide();
								}
							},
							error: function() {
								$status.removeClass('success').addClass('error').text('Network error. Please try again.').show();
								$btn.prop('disabled', false);
								$btnText.show();
								$btnSpinner.hide();
							}
						});
					});
				})(jQuery);
				</script>
			<?php else : ?>
				<!-- Admin Content: Debug Logs and Settings -->
				<div class="bbai-admin-content">
					<!-- Admin Header with Logout -->
					<div class="bbai-admin-header">
						<div class="bbai-admin-header-info">
							<h2 class="bbai-admin-header-title">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="margin-right: 10px; vertical-align: middle;">
									<path d="M10 1L19 10L10 19L1 10L10 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
									<circle cx="10" cy="10" r="2.5" fill="currentColor"/>
								</svg>
								<?php esc_html_e( 'Admin Panel', 'beepbeep-ai-alt-text-generator' ); ?>
							</h2>
							<p class="bbai-admin-header-subtitle">
								<?php esc_html_e( 'Debug Logs and Settings', 'beepbeep-ai-alt-text-generator' ); ?>
							</p>
						</div>
						<button type="button" class="bbai-admin-logout-btn" id="bbai-admin-logout-btn">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
								<path d="M6 14H3C2.44772 14 2 13.5523 2 13V3C2 2.44772 2.44772 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
								<path d="M10 11L13 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M13 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
							<?php esc_html_e( 'Log Out', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
					</div>

					<!-- Admin Tabs Navigation -->
					<div class="bbai-admin-tabs">
						<button type="button" class="bbai-admin-tab active" data-admin-tab="debug">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
								<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
								<circle cx="8" cy="8" r="2" fill="currentColor"/>
							</svg>
							<?php esc_html_e( 'Debug Logs', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
						<button type="button" class="bbai-admin-tab" data-admin-tab="settings">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
								<circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
								<path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
							<?php esc_html_e( 'Settings', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
					</div>

					<!-- Debug Logs Section -->
					<div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="debug">
						<div class="bbai-admin-section-header">
							<h3 class="bbai-admin-section-title"><?php esc_html_e( 'Debug Logs', 'beepbeep-ai-alt-text-generator' ); ?></h3>
						</div>
						<?php
						// Reuse debug logs content
						if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) :
							?>
							<div class="bbai-section">
								<div class="notice notice-warning">
									<p><?php esc_html_e( 'Debug logging is not available on this site. Please reinstall the logging module.', 'beepbeep-ai-alt-text-generator' ); ?></p>
								</div>
							</div>
							<?php
						else :
							// Inline debug logs content (same as debug tab)
							$debug_logs       = $debug_bootstrap['logs'] ?? array();
							$debug_stats      = $debug_bootstrap['stats'] ?? array();
							$debug_pagination = $debug_bootstrap['pagination'] ?? array();
							$debug_page       = max( 1, intval( $debug_pagination['page'] ?? 1 ) );
							$debug_pages      = max( 1, intval( $debug_pagination['total_pages'] ?? 1 ) );
							$debug_export_url = wp_nonce_url(
								admin_url( 'admin-post.php?action=bbai_debug_export' ),
								'bbai_debug_export'
							);
							?>
							<div class="bbai-dashboard-container" data-bbai-debug-panel>
								<!-- Debug Logs content (same as debug tab) - inline all content here -->
								<!-- Header Section -->
								<div class="bbai-debug-header">
									<h1 class="bbai-dashboard-title"><?php esc_html_e( 'Debug Logs', 'beepbeep-ai-alt-text-generator' ); ?></h1>
									<p class="bbai-dashboard-subtitle"><?php esc_html_e( 'Monitor API calls, queue events, and any errors generated by the plugin.', 'beepbeep-ai-alt-text-generator' ); ?></p>
									
									<!-- Usage Info -->
									<?php if ( isset( $usage_stats ) ) : ?>
									<div class="bbai-debug-usage-info">
										<?php
										printf(
											esc_html__( '%1$d of %2$d image descriptions generated this month', 'beepbeep-ai-alt-text-generator' ),
											$usage_stats['used'] ?? 0,
											$usage_stats['limit'] ?? 0
										);
										?>
										<span class="bbai-debug-usage-separator">•</span>
										<?php
										$reset_date = $usage_stats['reset_date'] ?? '';
										if ( ! empty( $reset_date ) ) {
											$reset_timestamp = strtotime( $reset_date );
											if ( $reset_timestamp !== false ) {
												$formatted_reset = date_i18n( 'F j, Y', $reset_timestamp );
												printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), esc_html( $formatted_reset ) );
											} else {
												printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), esc_html( $reset_date ) );
											}
										}
										?>
									</div>
									<?php endif; ?>
								</div>

								<!-- Log Statistics Card -->
								<div class="bbai-debug-stats-card">
									<div class="bbai-debug-stats-header">
										<div class="bbai-debug-stats-title">
											<h3><?php esc_html_e( 'Log Statistics', 'beepbeep-ai-alt-text-generator' ); ?></h3>
										</div>
										<div class="bbai-debug-stats-actions">
											<button type="button" class="bbai-debug-btn bbai-debug-btn--secondary" data-debug-clear>
												<?php esc_html_e( 'Clear Logs', 'beepbeep-ai-alt-text-generator' ); ?>
											</button>
											<a href="<?php echo esc_url( $debug_export_url ); ?>" class="bbai-debug-btn bbai-debug-btn--primary">
												<?php esc_html_e( 'Export CSV', 'beepbeep-ai-alt-text-generator' ); ?>
											</a>
										</div>
									</div>
									
									<!-- Stats Grid -->
									<div class="bbai-debug-stats-grid">
										<div class="bbai-debug-stat-item">
											<div class="bbai-debug-stat-label"><?php esc_html_e( 'TOTAL LOGS', 'beepbeep-ai-alt-text-generator' ); ?></div>
											<div class="bbai-debug-stat-value" data-debug-stat="total">
												<?php echo esc_html( number_format_i18n( intval( $debug_stats['total'] ?? 0 ) ) ); ?>
											</div>
										</div>
										<div class="bbai-debug-stat-item bbai-debug-stat-item--warning">
											<div class="bbai-debug-stat-label"><?php esc_html_e( 'WARNINGS', 'beepbeep-ai-alt-text-generator' ); ?></div>
											<div class="bbai-debug-stat-value bbai-debug-stat-value--warning" data-debug-stat="warnings">
												<?php echo esc_html( number_format_i18n( intval( $debug_stats['warnings'] ?? 0 ) ) ); ?>
											</div>
										</div>
										<div class="bbai-debug-stat-item bbai-debug-stat-item--error">
											<div class="bbai-debug-stat-label"><?php esc_html_e( 'ERRORS', 'beepbeep-ai-alt-text-generator' ); ?></div>
											<div class="bbai-debug-stat-value bbai-debug-stat-value--error" data-debug-stat="errors">
												<?php echo esc_html( number_format_i18n( intval( $debug_stats['errors'] ?? 0 ) ) ); ?>
											</div>
										</div>
										<div class="bbai-debug-stat-item">
											<div class="bbai-debug-stat-label"><?php esc_html_e( 'LAST API EVENT', 'beepbeep-ai-alt-text-generator' ); ?></div>
											<div class="bbai-debug-stat-value bbai-debug-stat-value--small" data-debug-stat="last_api">
												<?php echo esc_html( $debug_stats['last_api'] ?? '—' ); ?>
											</div>
										</div>
									</div>
								</div>

								<!-- Filters Panel -->
								<div class="bbai-debug-filters-card">
									<form data-debug-filter class="bbai-debug-filters-form">
										<div class="bbai-debug-filter-group">
											<label for="bbai-debug-level" class="bbai-debug-filter-label">
												<?php esc_html_e( 'Level', 'beepbeep-ai-alt-text-generator' ); ?>
											</label>
											<select id="bbai-debug-level" name="level" class="bbai-debug-filter-input">
												<option value=""><?php esc_html_e( 'All levels', 'beepbeep-ai-alt-text-generator' ); ?></option>
												<option value="info"><?php esc_html_e( 'Info', 'beepbeep-ai-alt-text-generator' ); ?></option>
												<option value="warning"><?php esc_html_e( 'Warning', 'beepbeep-ai-alt-text-generator' ); ?></option>
												<option value="error"><?php esc_html_e( 'Error', 'beepbeep-ai-alt-text-generator' ); ?></option>
											</select>
										</div>
										<div class="bbai-debug-filter-group">
											<label for="bbai-debug-date" class="bbai-debug-filter-label">
												<?php esc_html_e( 'Date', 'beepbeep-ai-alt-text-generator' ); ?>
											</label>
											<input type="date" id="bbai-debug-date" name="date" class="bbai-debug-filter-input" placeholder="dd/mm/yyyy">
										</div>
										<div class="bbai-debug-filter-group bbai-debug-filter-group--search">
											<label for="bbai-debug-search" class="bbai-debug-filter-label">
												<?php esc_html_e( 'Search', 'beepbeep-ai-alt-text-generator' ); ?>
											</label>
											<div class="bbai-debug-search-wrapper">
												<svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-debug-search-icon">
													<circle cx="7" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/>
													<path d="M10 10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
												</svg>
												<input type="search" id="bbai-debug-search" name="search" placeholder="<?php esc_attr_e( 'Search logs…', 'beepbeep-ai-alt-text-generator' ); ?>" class="bbai-debug-filter-input bbai-debug-filter-input--search">
											</div>
										</div>
										<div class="bbai-debug-filter-actions">
											<button type="submit" class="bbai-debug-btn bbai-debug-btn--primary">
												<?php esc_html_e( 'Apply', 'beepbeep-ai-alt-text-generator' ); ?>
											</button>
											<button type="button" class="bbai-debug-btn bbai-debug-btn--ghost" data-debug-reset>
												<?php esc_html_e( 'Reset', 'beepbeep-ai-alt-text-generator' ); ?>
											</button>
										</div>
									</form>
								</div>

								<!-- Table Card -->
								<div class="bbai-debug-table-card">
									<table class="bbai-debug-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'TIMESTAMP', 'beepbeep-ai-alt-text-generator' ); ?></th>
												<th><?php esc_html_e( 'LEVEL', 'beepbeep-ai-alt-text-generator' ); ?></th>
												<th><?php esc_html_e( 'MESSAGE', 'beepbeep-ai-alt-text-generator' ); ?></th>
												<th><?php esc_html_e( 'CONTEXT', 'beepbeep-ai-alt-text-generator' ); ?></th>
											</tr>
										</thead>
										<tbody data-debug-rows>
											<?php if ( ! empty( $debug_logs ) ) : ?>
												<?php
												$row_index = 0;
												foreach ( $debug_logs as $log ) :
													++$row_index;
													$level          = strtolower( $log['level'] ?? 'info' );
													$level_slug     = preg_replace( '/[^a-z0-9_-]/i', '', $level ) ?: 'info';
													$context_attr   = '';
													$context_source = $log['context'] ?? array();
													if ( ! empty( $context_source ) ) {
														if ( is_array( $context_source ) ) {
															$json         = wp_json_encode( $context_source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
															$context_attr = base64_encode( $json );
														} else {
															$context_str = (string) $context_source;
															$decoded     = json_decode( $context_str, true );
															if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
																$json         = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
																$context_attr = base64_encode( $json );
															} else {
																$context_attr = base64_encode( $context_str );
															}
														}
													}

													// Format date: "Nov 13, 2025 — 12:45 PM"
													$created_at     = $log['created_at'] ?? '';
													$formatted_date = $created_at;
													if ( $created_at ) {
														$timestamp = strtotime( $created_at );
														if ( $timestamp !== false ) {
															$formatted_date = date( 'M j, Y — g:i A', $timestamp );
														}
													}

													// Severity badge classes
													$badge_class = 'bbai-debug-badge bbai-debug-badge--' . esc_attr( $level_slug );
													?>
												<tr class="bbai-debug-table-row" data-row-index="<?php echo esc_attr( $row_index ); ?>">
													<td class="bbai-debug-table-cell bbai-debug-table-cell--timestamp">
														<?php echo esc_html( $formatted_date ); ?>
													</td>
													<td class="bbai-debug-table-cell bbai-debug-table-cell--level">
														<span class="<?php echo esc_attr( $badge_class ); ?>">
															<span class="bbai-debug-badge-text"><?php echo esc_html( ucfirst( $level_slug ) ); ?></span>
														</span>
													</td>
													<td class="bbai-debug-table-cell bbai-debug-table-cell--message">
														<?php echo esc_html( $log['message'] ?? '' ); ?>
													</td>
													<td class="bbai-debug-table-cell bbai-debug-table-cell--context">
														<?php if ( $context_attr ) : ?>
															<button type="button" 
																	class="bbai-debug-context-btn" 
																	data-context-data="<?php echo esc_attr( $context_attr ); ?>"
																	data-row-index="<?php echo esc_attr( $row_index ); ?>">
																<?php esc_html_e( 'Log Context', 'beepbeep-ai-alt-text-generator' ); ?>
															</button>
														<?php else : ?>
															<span class="bbai-debug-context-empty">—</span>
														<?php endif; ?>
													</td>
												</tr>
													<?php if ( $context_attr ) : ?>
												<tr class="bbai-debug-context-row" data-row-index="<?php echo esc_attr( $row_index ); ?>" style="display: none;">
													<td colspan="4" class="bbai-debug-context-cell">
														<div class="bbai-debug-context-content">
															<pre class="bbai-debug-context-json"></pre>
														</div>
													</td>
												</tr>
												<?php endif; ?>
												<?php endforeach; ?>
											<?php else : ?>
												<tr>
													<td colspan="4" class="bbai-debug-table-empty">
														<?php esc_html_e( 'No logs recorded yet.', 'beepbeep-ai-alt-text-generator' ); ?>
													</td>
												</tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>

								<!-- Pagination -->
								<?php if ( $debug_pages > 1 ) : ?>
								<div class="bbai-debug-pagination">
									<button type="button" class="bbai-debug-pagination-btn" data-debug-page="prev" <?php disabled( $debug_page <= 1 ); ?>>
										<?php esc_html_e( 'Previous', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
									<span class="bbai-debug-pagination-info" data-debug-page-indicator>
										<?php
											printf(
												esc_html__( 'Page %1$s of %2$s', 'beepbeep-ai-alt-text-generator' ),
												esc_html( number_format_i18n( $debug_page ) ),
												esc_html( number_format_i18n( $debug_pages ) )
											);
										?>
									</span>
									<button type="button" class="bbai-debug-pagination-btn" data-debug-page="next" <?php disabled( $debug_page >= $debug_pages ); ?>>
										<?php esc_html_e( 'Next', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								</div>
								<?php endif; ?>

								<div class="bbai-debug-toast" data-debug-toast hidden></div>
							</div>
							
							<!-- Context Button Handler (same as debug tab) -->
							<script>
							(function() {
								function toggleContext(button) {
									var contextData = button.getAttribute('data-context-data');
									var rowIndex = button.getAttribute('data-row-index');
									
									if (!contextData || !rowIndex) {
										console.error('Missing context data or row index');
										return;
									}
									
									// Find the parent row
									var row = button;
									while (row && row.tagName !== 'TR') {
										row = row.parentElement;
									}
									if (!row) return;
									
									// Find the context row
									var contextRow = null;
									var nextSibling = row.nextElementSibling;
									while (nextSibling) {
										if (nextSibling.classList.contains('bbai-debug-context-row') && 
											nextSibling.getAttribute('data-row-index') === rowIndex) {
											contextRow = nextSibling;
											break;
										}
										if (!nextSibling.classList.contains('bbai-debug-context-row')) {
											break;
										}
										nextSibling = nextSibling.nextElementSibling;
									}
									
									// If context row doesn't exist, create it
									if (!contextRow) {
										contextRow = document.createElement('tr');
										contextRow.className = 'bbai-debug-context-row';
										contextRow.setAttribute('data-row-index', rowIndex);
										contextRow.style.display = 'none';
										
										var cell = document.createElement('td');
										cell.className = 'bbai-debug-context-cell';
										cell.colSpan = 4;
										
										var content = document.createElement('div');
										content.className = 'bbai-debug-context-content';
										
										var pre = document.createElement('pre');
										pre.className = 'bbai-debug-context-json';
										
										content.appendChild(pre);
										cell.appendChild(content);
										contextRow.appendChild(cell);
										
										// Insert after the current row
										if (row.nextSibling) {
											row.parentNode.insertBefore(contextRow, row.nextSibling);
										} else {
											row.parentNode.appendChild(contextRow);
										}
									}
									
									var preElement = contextRow.querySelector('pre.bbai-debug-context-json');
									if (!preElement) return;
									
									var isVisible = contextRow.style.display !== 'none';
									
									if (isVisible) {
										// Hide
										contextRow.style.display = 'none';
										button.textContent = 'Log Context';
										button.classList.remove('is-expanded');
									} else {
										// Show - decode and display
										var decoded = null;
										
										// Try to decode base64
										try {
											if (/^[A-Za-z0-9+\/]*={0,2}$/.test(contextData)) {
												var decodedStr = decodeURIComponent(escape(atob(contextData)));
												decoded = JSON.parse(decodedStr);
											}
										} catch(e1) {
											// Try direct JSON parse
											try {
												decoded = JSON.parse(contextData);
											} catch(e2) {
												// Try URL decode then parse
												try {
													decoded = JSON.parse(decodeURIComponent(contextData));
												} catch(e3) {
													decoded = { error: 'Unable to decode context data' };
												}
											}
										}
										
										var output = JSON.stringify(decoded, null, 2);
										preElement.textContent = output;
										contextRow.style.display = 'table-row';
										button.textContent = 'Hide Context';
										button.classList.add('is-expanded');
									}
								}
								
								// Bind to all context buttons
								document.addEventListener('click', function(e) {
									var btn = e.target;
									while (btn && btn !== document.body) {
										if (btn.classList && btn.classList.contains('bbai-debug-context-btn')) {
											e.preventDefault();
											e.stopPropagation();
											toggleContext(btn);
											return;
										}
										btn = btn.parentElement;
									}
								});
							})();
							</script>
						<?php endif; ?>
					</div>

					<!-- Settings Section -->
					<div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="settings" style="display: none;">
						<div class="bbai-admin-section-header">
							<h3 class="bbai-admin-section-title"><?php esc_html_e( 'Settings', 'beepbeep-ai-alt-text-generator' ); ?></h3>
						</div>
						<?php
						// Reuse settings content from settings tab (same as starting from line 2716)
						// Pull fresh usage from backend to avoid stale cache in Settings
						if ( isset( $this->api_client ) ) {
							$live = $this->api_client->get_usage();
							if ( is_array( $live ) && ! empty( $live ) ) {
								Usage_Tracker::update_usage( $live ); }
						}
						$usage_box = Usage_Tracker::get_stats_display();
						$o         = wp_parse_args( $opts, array() );

						// Check for license plan first
						$has_license  = $this->api_client->has_active_license();
						$license_data = $this->api_client->get_license_data();

						$plan = $usage_box['plan'] ?? 'free';

						// If license is active, use license plan
						if ( $has_license && $license_data && isset( $license_data['organization'] ) ) {
							$license_plan = strtolower( $license_data['organization']['plan'] ?? 'free' );
							if ( $license_plan !== 'free' ) {
								$plan = $license_plan;
							}
						}

						$is_pro        = $plan === 'pro';
						$is_agency     = $plan === 'agency';
						$usage_percent = $usage_box['limit'] > 0 ? ( $usage_box['used'] / $usage_box['limit'] * 100 ) : 0;

						// Determine plan badge text
						if ( $is_agency ) {
							$plan_badge_text = esc_html__( 'AGENCY', 'beepbeep-ai-alt-text-generator' );
						} elseif ( $is_pro ) {
							$plan_badge_text = esc_html__( 'PRO', 'beepbeep-ai-alt-text-generator' );
						} else {
							$plan_badge_text = esc_html__( 'FREE', 'beepbeep-ai-alt-text-generator' );
						}
						?>
						<!-- Settings Page Content (full content from settings tab) -->
						<div class="bbai-settings-page">
							<!-- Site-Wide Settings Banner -->
							<div class="bbai-settings-sitewide-banner">
								<svg class="bbai-settings-sitewide-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
									<circle cx="10" cy="10" r="8" stroke="#3b82f6" stroke-width="2" fill="none"/>
									<path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
								</svg>
								<div class="bbai-settings-sitewide-content">
									<strong class="bbai-settings-sitewide-title"><?php esc_html_e( 'Site-Wide Settings', 'beepbeep-ai-alt-text-generator' ); ?></strong>
									<span class="bbai-settings-sitewide-text">
										<?php esc_html_e( 'These settings apply to all users on this WordPress site.', 'beepbeep-ai-alt-text-generator' ); ?>
									</span>
								</div>
							</div>

							<!-- Plan Summary Card -->
							<div class="bbai-settings-plan-summary-card">
								<div class="bbai-settings-plan-badge-top">
									<span class="bbai-settings-plan-badge-text"><?php echo esc_html( $plan_badge_text ); ?></span>
								</div>
								<div class="bbai-settings-plan-quota">
									<div class="bbai-settings-plan-quota-meter">
										<span class="bbai-settings-plan-quota-used"><?php echo esc_html( $usage_box['used'] ); ?></span>
										<span class="bbai-settings-plan-quota-divider">/</span>
										<span class="bbai-settings-plan-quota-limit"><?php echo esc_html( $usage_box['limit'] ); ?></span>
									</div>
									<div class="bbai-settings-plan-quota-label">
										<?php esc_html_e( 'image descriptions', 'beepbeep-ai-alt-text-generator' ); ?>
									</div>
								</div>
								<div class="bbai-settings-plan-info">
									<?php if ( ! $is_pro && ! $is_agency ) : ?>
										<div class="bbai-settings-plan-info-item">
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
												<circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
												<path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
											</svg>
											<span>
												<?php
												if ( isset( $usage_box['reset_date'] ) ) {
													printf(
														esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ),
														'<strong>' . esc_html( $usage_box['reset_date'] ) . '</strong>'
													);
												} else {
													esc_html_e( 'Monthly quota', 'beepbeep-ai-alt-text-generator' );
												}
												?>
											</span>
										</div>
										<div class="bbai-settings-plan-info-item">
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
												<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
												<circle cx="8" cy="8" r="2" fill="currentColor"/>
											</svg>
											<span><?php esc_html_e( 'Shared across all users', 'beepbeep-ai-alt-text-generator' ); ?></span>
										</div>
									<?php elseif ( $is_agency ) : ?>
										<div class="bbai-settings-plan-info-item">
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
												<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<span><?php esc_html_e( 'Multi-site license', 'beepbeep-ai-alt-text-generator' ); ?></span>
										</div>
										<div class="bbai-settings-plan-info-item">
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
												<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<span><?php printf( esc_html__( 'Resets %s', 'beepbeep-ai-alt-text-generator' ), '<strong>' . esc_html( $usage_box['reset_date'] ?? 'Monthly' ) . '</strong>' ); ?></span>
										</div>
									<?php else : ?>
										<div class="bbai-settings-plan-info-item">
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
												<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<span><?php esc_html_e( '1,000 generations per month', 'beepbeep-ai-alt-text-generator' ); ?></span>
										</div>
										<div class="bbai-settings-plan-info-item">
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
												<path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<span><?php esc_html_e( 'Priority support', 'beepbeep-ai-alt-text-generator' ); ?></span>
										</div>
									<?php endif; ?>
								</div>
								<?php if ( ! $is_pro && ! $is_agency ) : ?>
								<button type="button" class="bbai-settings-plan-upgrade-btn-large" data-action="show-upgrade-modal">
									<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?>
								</button>
								<?php endif; ?>
							</div>

							<!-- License Management Card -->
							<?php
							// Reuse license variables if already set above
							if ( ! isset( $has_license ) ) {
								$has_license  = $this->api_client->has_active_license();
								$license_data = $this->api_client->get_license_data();
							}
							?>

							<div class="bbai-settings-card">
								<div class="bbai-settings-card-header">
									<div class="bbai-settings-card-header-icon">
										<span class="bbai-settings-card-icon-emoji">🔑</span>
									</div>
									<h3 class="bbai-settings-card-title"><?php esc_html_e( 'License', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								</div>

								<?php if ( $has_license && $license_data ) : ?>
									<?php
									$org = $license_data['organization'] ?? null;
									if ( $org ) :
										?>
									<!-- Active License Display -->
									<div class="bbai-settings-license-active">
										<div class="bbai-settings-license-status">
											<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
												<circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
												<path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<div>
												<div class="bbai-settings-license-title"><?php esc_html_e( 'License Active', 'beepbeep-ai-alt-text-generator' ); ?></div>
												<div class="bbai-settings-license-subtitle"><?php echo esc_html( $org['name'] ?? '' ); ?></div>
											</div>
										</div>
										<button type="button" class="bbai-settings-license-deactivate-btn" data-action="deactivate-license">
											<?php esc_html_e( 'Deactivate', 'beepbeep-ai-alt-text-generator' ); ?>
										</button>
									</div>
									
										<?php
										// Show site usage for agency licenses when authenticated
										$is_authenticated_for_license = $this->api_client->is_authenticated();
										$is_agency_license            = isset( $license_data['organization']['plan'] ) && $license_data['organization']['plan'] === 'agency';

										if ( $is_agency_license && $is_authenticated_for_license ) :
											?>
									<!-- License Site Usage Section -->
									<div class="bbai-settings-license-sites" id="bbai-license-sites">
										<div class="bbai-settings-license-sites-header">
											<h3 class="bbai-settings-license-sites-title">
												<svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
													<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
													<circle cx="8" cy="8" r="2" fill="currentColor"/>
												</svg>
												<?php esc_html_e( 'Sites Using This License', 'beepbeep-ai-alt-text-generator' ); ?>
											</h3>
										</div>
										<div class="bbai-settings-license-sites-content" id="bbai-license-sites-content">
											<div class="bbai-settings-license-sites-loading">
												<span class="bbai-spinner"></span>
												<?php esc_html_e( 'Loading site usage...', 'beepbeep-ai-alt-text-generator' ); ?>
											</div>
										</div>
									</div>
									<?php elseif ( $is_agency_license && ! $is_authenticated_for_license ) : ?>
									<!-- Prompt to login for site usage -->
									<div class="bbai-settings-license-sites-auth">
										<p class="bbai-settings-license-sites-auth-text">
											<?php esc_html_e( 'Log in to view site usage and generation statistics for this license.', 'beepbeep-ai-alt-text-generator' ); ?>
										</p>
										<button type="button" class="bbai-settings-license-sites-auth-btn" data-action="show-auth-modal" data-auth-tab="login">
											<?php esc_html_e( 'Log In', 'beepbeep-ai-alt-text-generator' ); ?>
										</button>
									</div>
									<?php endif; ?>
									
									<?php endif; ?>
								<?php else : ?>
									<!-- License Activation Form -->
									<div class="bbai-settings-license-form">
										<p class="bbai-settings-license-description">
											<?php esc_html_e( 'Enter your license key to activate this site. Agency licenses can be used across multiple sites.', 'beepbeep-ai-alt-text-generator' ); ?>
										</p>
										<form id="license-activation-form">
											<div class="bbai-settings-license-input-group">
												<label for="license-key-input" class="bbai-settings-license-label">
													<?php esc_html_e( 'License Key', 'beepbeep-ai-alt-text-generator' ); ?>
												</label>
												<input type="text"
														id="license-key-input"
														name="license_key"
														class="bbai-settings-license-input"
														placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
														pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
														required>
											</div>
											<div id="license-activation-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
											<button type="submit" id="activate-license-btn" class="bbai-settings-license-activate-btn">
												<?php esc_html_e( 'Activate License', 'beepbeep-ai-alt-text-generator' ); ?>
											</button>
										</form>
									</div>
								<?php endif; ?>
							</div>

							<!-- Account Management Card -->
							<div class="bbai-settings-card">
								<div class="bbai-settings-card-header">
									<div class="bbai-settings-card-header-icon">
										<span class="bbai-settings-card-icon-emoji">👤</span>
									</div>
									<h3 class="bbai-settings-card-title"><?php esc_html_e( 'Account Management', 'beepbeep-ai-alt-text-generator' ); ?></h3>
								</div>
								
								<?php if ( ! $is_pro && ! $is_agency ) : ?>
								<div class="bbai-settings-account-info-banner">
									<span><?php esc_html_e( 'You are on the free plan.', 'beepbeep-ai-alt-text-generator' ); ?></span>
								</div>
								<div class="bbai-settings-account-upgrade-link">
									<button type="button" class="bbai-settings-account-upgrade-btn" data-action="show-upgrade-modal">
										<?php esc_html_e( 'Upgrade Now', 'beepbeep-ai-alt-text-generator' ); ?>
									</button>
								</div>
								<?php else : ?>
								<div class="bbai-settings-account-status">
									<span class="bbai-settings-account-status-label"><?php esc_html_e( 'Current Plan:', 'beepbeep-ai-alt-text-generator' ); ?></span>
									<span class="bbai-settings-account-status-value">
									<?php
									if ( $is_agency ) {
										esc_html_e( 'Agency', 'beepbeep-ai-alt-text-generator' );
									} else {
										esc_html_e( 'Pro', 'beepbeep-ai-alt-text-generator' );
									}
									?>
									</span>
								</div>
									<?php
									// Check if using license vs authenticated account
									$is_authenticated_for_account = $this->api_client->is_authenticated();
									$is_license_only              = $has_license && ! $is_authenticated_for_account;

									if ( $is_license_only ) :
										// License-based plan - provide contact info
										?>
								<div class="bbai-settings-account-actions">
									<div class="bbai-settings-account-action-info">
										<p><strong><?php esc_html_e( 'License-Based Plan', 'beepbeep-ai-alt-text-generator' ); ?></strong></p>
										<p><?php esc_html_e( 'Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'beepbeep-ai-alt-text-generator' ); ?></p>
										<ul>
											<li><?php esc_html_e( 'Contact your license administrator', 'beepbeep-ai-alt-text-generator' ); ?></li>
											<li><?php esc_html_e( 'Email support for billing inquiries', 'beepbeep-ai-alt-text-generator' ); ?></li>
											<li><?php esc_html_e( 'View license details in the License section above', 'beepbeep-ai-alt-text-generator' ); ?></li>
										</ul>
									</div>
								</div>
										<?php
								elseif ( $is_authenticated_for_account ) :
									// Authenticated user - show Stripe portal
									?>
								<div class="bbai-settings-account-actions">
									<button type="button" class="bbai-settings-account-action-btn" data-action="manage-subscription">
										<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
											<path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
											<circle cx="8" cy="8" r="2" fill="currentColor"/>
										</svg>
										<span><?php esc_html_e( 'Manage Subscription', 'beepbeep-ai-alt-text-generator' ); ?></span>
									</button>
									<div class="bbai-settings-account-action-info">
										<p><?php esc_html_e( 'In Stripe Customer Portal you can:', 'beepbeep-ai-alt-text-generator' ); ?></p>
										<ul>
											<li><?php esc_html_e( 'View and download invoices', 'beepbeep-ai-alt-text-generator' ); ?></li>
											<li><?php esc_html_e( 'Update payment method', 'beepbeep-ai-alt-text-generator' ); ?></li>
											<li><?php esc_html_e( 'View payment history', 'beepbeep-ai-alt-text-generator' ); ?></li>
											<li><?php esc_html_e( 'Cancel or modify subscription', 'beepbeep-ai-alt-text-generator' ); ?></li>
										</ul>
									</div>
								</div>
								<?php endif; ?>
								<?php endif; ?>
							</div>

							<!-- Settings Form -->
							<form method="post" action="options.php" autocomplete="off">
								<?php settings_fields( 'bbai_group' ); ?>

								<!-- Generation Settings Card -->
								<div class="bbai-settings-card">
									<h3 class="bbai-settings-generation-title"><?php esc_html_e( 'Generation Settings', 'beepbeep-ai-alt-text-generator' ); ?></h3>

									<div class="bbai-settings-form-group">
										<div class="bbai-settings-form-field bbai-settings-form-field--toggle">
											<div class="bbai-settings-form-field-content">
												<label for="bbai-enable-on-upload" class="bbai-settings-form-label">
													<?php esc_html_e( 'Auto-generate on Image Upload', 'beepbeep-ai-alt-text-generator' ); ?>
												</label>
												<p class="bbai-settings-form-description">
													<?php esc_html_e( 'Automatically generate alt text when new images are uploaded to your media library.', 'beepbeep-ai-alt-text-generator' ); ?>
												</p>
											</div>
											<label class="bbai-settings-toggle">
												<input 
													type="checkbox" 
													id="bbai-enable-on-upload"
													name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_on_upload]" 
													value="1"
													<?php checked( ! empty( $o['enable_on_upload'] ?? true ) ); ?>
												>
												<span class="bbai-settings-toggle-slider"></span>
											</label>
										</div>
									</div>

									<div class="bbai-settings-form-group">
										<label for="bbai-tone" class="bbai-settings-form-label">
											<?php esc_html_e( 'Tone & Style', 'beepbeep-ai-alt-text-generator' ); ?>
										</label>
										<input
											type="text"
											id="bbai-tone"
											name="<?php echo esc_attr( self::OPTION_KEY ); ?>[tone]"
											value="<?php echo esc_attr( $o['tone'] ?? 'professional, accessible' ); ?>"
											placeholder="<?php esc_attr_e( 'professional, accessible', 'beepbeep-ai-alt-text-generator' ); ?>"
											class="bbai-settings-form-input"
										/>
									</div>

									<div class="bbai-settings-form-group">
										<label for="bbai-custom-prompt" class="bbai-settings-form-label">
											<?php esc_html_e( 'Additional Instructions', 'beepbeep-ai-alt-text-generator' ); ?>
										</label>
										<textarea
											id="bbai-custom-prompt"
											name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_prompt]"
											rows="4"
											placeholder="<?php esc_attr_e( 'Enter any specific instructions for the AI...', 'beepbeep-ai-alt-text-generator' ); ?>"
											class="bbai-settings-form-textarea"
										><?php echo esc_textarea( $o['custom_prompt'] ?? '' ); ?></textarea>
									</div>

									<div class="bbai-settings-form-actions">
										<button type="submit" class="bbai-settings-save-btn">
											<?php esc_html_e( 'Save Settings', 'beepbeep-ai-alt-text-generator' ); ?>
										</button>
									</div>
								</div>
							</form>

							<!-- Hidden nonce for AJAX requests -->
							<input type="hidden" id="license-nonce" value="<?php echo esc_attr( wp_create_nonce( 'beepbeepai_nonce' ) ); ?>">
						</div>
					</div>
				</div>
				
				<script>
				(function($) {
					'use strict';
					
					// Admin tab switching
					$('.bbai-admin-tab').on('click', function(e) {
						e.preventDefault();
						
						const $tab = $(this);
						const tabName = $tab.data('admin-tab');
						
						// Update active tab
						$('.bbai-admin-tab').removeClass('active');
						$tab.addClass('active');
						
						// Show/hide content
						$('.bbai-admin-tab-content').hide();
						$('.bbai-admin-tab-content[data-admin-tab-content="' + tabName + '"]').show();
						
						// Load license site usage when switching to settings tab
						if (tabName === 'settings' && typeof window.loadLicenseSiteUsage === 'function') {
							// Small delay to ensure DOM is updated
							setTimeout(function() {
								window.loadLicenseSiteUsage();
							}, 100);
						}
						
						// Update URL hash without scrolling
						if (history.pushState) {
							history.pushState(null, null, '#' + tabName);
						}
					});
					
					// Check for hash on load
					const hash = window.location.hash.replace('#', '');
					if (hash === 'debug' || hash === 'settings') {
						$('.bbai-admin-tab[data-admin-tab="' + hash + '"]').trigger('click');
					}
					
					// Logout handler
					$('#bbai-admin-logout-btn').on('click', function(e) {
						e.preventDefault();
						
						if (!confirm('<?php echo esc_js( __( 'Are you sure you want to log out of the admin panel?', 'beepbeep-ai-alt-text-generator' ) ); ?>')) {
							return;
						}
						
						const $btn = $(this);
						$btn.prop('disabled', true);
						
						$.ajax({
							url: window.bbai_ajax.ajaxurl,
							type: 'POST',
							data: {
								action: 'bbai_admin_logout',
								nonce: window.bbai_ajax.nonce
							},
							success: function(response) {
								if (response.success) {
									window.location.href = response.data.redirect || window.location.href;
								} else {
									alert(response.data?.message || 'Logout failed');
									$btn.prop('disabled', false);
								}
							},
							error: function() {
								alert('Network error. Please try again.');
								$btn.prop('disabled', false);
							}
						});
					});
				})(jQuery);
				</script>
			<?php endif; ?>
			</div><!-- .bbai-container -->
			
			<!-- Footer -->
			<div style="text-align: center; padding: 24px 0; margin-top: 48px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">
				<?php esc_html_e( 'BeepBeep AI • WordPress AI Tools', 'beepbeep-ai-alt-text-generator' ); ?> — <a href="<?php echo esc_url( 'https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/' ); ?>" target="_blank" rel="noopener noreferrer" style="color: #14b8a6; text-decoration: none;"><?php echo esc_html__( 'WordPress.org Plugin', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</div>
		</div>
		
				<?php
		endif; // End tab check (dashboard/library/guide/debug/settings/credit-usage)

			// Include upgrade modal OUTSIDE of tab conditionals so it's always available
			// Set up currency for upgrade modal - improved detection
			$locale        = get_locale();
			$locale_lower  = strtolower( $locale );
			$locale_prefix = substr( $locale_lower, 0, 2 );

			// Default to USD
			$currency = array(
				'symbol'  => '$',
				'code'    => 'USD',
				'pro'     => 14.99,
				'agency'  => 59.99,
				'credits' => 11.99,
			);

			// UK/British locales - GBP (£)
			$uk_locales = array( 'en_gb', 'en_im', 'en_je', 'en_gg', 'en_uk', 'en_au', 'en_nz', 'en_za' );
			if ( in_array( $locale_lower, $uk_locales ) || strpos( $locale_lower, 'en_gb' ) !== false ) {
				$currency = array(
					'symbol'  => '£',
					'code'    => 'GBP',
					'pro'     => 12.99,
					'agency'  => 49.99,
					'credits' => 9.99,
				);
			}
			// European locales - EUR (€)
			elseif ( in_array( $locale_prefix, array( 'de', 'fr', 'it', 'es', 'pt', 'nl', 'pl', 'el', 'cs', 'sk', 'hu', 'ro', 'bg', 'hr', 'sl', 'lt', 'lv', 'et', 'fi', 'sv', 'da', 'be', 'at', 'ie', 'lu', 'mt', 'cy' ) ) ) {
				$currency = array(
					'symbol'  => '€',
					'code'    => 'EUR',
					'pro'     => 12.99,
					'agency'  => 49.99,
					'credits' => 9.99,
				);
			}
			// US/Canada locales - USD ($)
			elseif ( in_array( $locale_lower, array( 'en_us', 'en_ca' ) ) || ( $locale_prefix === 'en' && ! in_array( $locale_lower, $uk_locales ) ) ) {
				$currency = array(
					'symbol'  => '$',
					'code'    => 'USD',
					'pro'     => 14.99,
					'agency'  => 59.99,
					'credits' => 11.99,
				);
			}
			// Check for European countries
			elseif ( in_array( substr( $locale, 0, 2 ), array( 'de', 'fr', 'it', 'es', 'pt', 'nl', 'pl', 'el', 'cs', 'sk', 'hu', 'ro', 'bg', 'hr', 'sl', 'lt', 'lv', 'et', 'fi', 'sv', 'da' ) ) ) {
				$currency = array(
					'symbol'  => '€',
					'code'    => 'EUR',
					'pro'     => 12.99,
					'agency'  => 29,
					'credits' => 9.99,
				);
			}

			// Include upgrade modal - always available for all tabs
			$checkout_prices = $this->get_checkout_price_ids();
			include BBAI_PLUGIN_DIR . 'templates/upgrade-modal.php';
	}

	/**
	 * Sanitize error messages to prevent exposing sensitive API information
	 */
	private function sanitize_error_message( $message ) {
		if ( ! is_string( $message ) ) {
			return $message;
		}

		// Remove URLs, tokens, API keys, and other sensitive data
		$sanitized = preg_replace(
			array(
				'/https?:\/\/[^\s]+/i',  // Remove URLs
				'/Bearer\s+[A-Za-z0-9\-_\.]+/i',  // Remove Bearer tokens
				'/token[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove token values
				'/api[_-]?key[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove API keys
				'/secret[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove secrets
				'/password[=:]\s*[^\s]+/i',  // Remove passwords
			),
			'[REDACTED]',
			$message
		);

		return $sanitized;
	}

	private function build_prompt( $attachment_id, $opts, $existing_alt = '', bool $is_retry = false, array $feedback = array() ) {
		$file       = get_attached_file( $attachment_id );
		$title_raw  = get_the_title( $attachment_id );
		$filename   = $file ? wp_basename( $file ) : ( is_string( $title_raw ) ? $title_raw : '' );
		$title      = is_string( $title_raw ) ? $title_raw : '';
		$caption    = wp_get_attachment_caption( $attachment_id );
		$parent_raw = get_post_field( 'post_title', wp_get_post_parent_id( $attachment_id ) );
		$parent     = is_string( $parent_raw ) ? $parent_raw : '';
		$lang_raw   = $opts['language'] ?? 'en-GB';
		if ( $lang_raw === 'custom' && ! empty( $opts['language_custom'] ) ) {
			$lang = sanitize_text_field( $opts['language_custom'] );
		} else {
			$lang = $lang_raw;
		}
		$tone = $opts['tone'] ?? 'professional, accessible';
		$max  = max( 4, intval( $opts['max_words'] ?? 16 ) );

		$existing_alt = is_string( $existing_alt ) ? trim( $existing_alt ) : '';
		$context_bits = array_filter( array( $title, $caption, $parent, $existing_alt ? ( 'Existing ALT: ' . $existing_alt ) : '' ) );
		$context      = $context_bits ? ( 'Context: ' . implode( ' | ', $context_bits ) ) : '';

		$custom      = trim( $opts['custom_prompt'] ?? '' );
		$instruction = "Write concise, descriptive ALT text in {$lang} for the provided image. "
				. "Limit to {$max} words. Tone: {$tone}. "
				. 'Describe the primary subject with concrete nouns; include one visible colour/texture and any clearly visible background. '
				. 'Only describe what is visible; no guessing about intent, brand, or location unless unmistakable. '
				. "If the image is a text/wordmark/logo (e.g., filename/title contains 'logo', 'icon', 'wordmark', or the image is mostly text), respond with a short accurate phrase like 'Red “TEST” wordmark' rather than a scene description. "
				. "Avoid 'image of' / 'photo of' and never output placeholders like 'test' or 'sample'. "
				. 'Return only the ALT text sentence.';

		if ( $existing_alt ) {
			$instruction .= ' The previous ALT text is provided for context and must be improved upon.';
		}

		if ( $is_retry ) {
			$instruction .= ' The previous attempt was rejected; ensure this version corrects the issues listed below and adds concrete, specific detail.';
		}

		$feedback_lines = array_filter( array_map( 'trim', $feedback ) );
		$feedback_block = '';
		if ( $feedback_lines ) {
			$feedback_block = "\nReviewer feedback:";
			foreach ( $feedback_lines as $line ) {
				$feedback_block .= "\n- " . sanitize_text_field( $line );
			}
			$feedback_block .= "\n";
		}

		$prompt = ( $custom ? $custom . "\n\n" : '' )
				. $instruction
				. "\nFilename: {$filename}\n{$context}\n" . $feedback_block;
		return apply_filters( 'bbai_prompt', $prompt, $attachment_id, $opts );
	}

	private function is_image( $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );
		return strpos( (string) $mime, 'image/' ) === 0;
	}

	public function invalidate_stats_cache() {
		wp_cache_delete( 'bbai_stats', 'bbai' );
		delete_transient( 'bbai_stats_v3' );
		$this->stats_cache = null;
	}

	public function get_media_stats() {
		try {
			// Check in-memory cache first
			if ( is_array( $this->stats_cache ) ) {
				return $this->stats_cache;
			}

			// Check object cache (Redis/Memcached if available)
			$cache_key   = 'bbai_stats';
			$cache_group = 'bbai';
			$cached      = wp_cache_get( $cache_key, $cache_group );
			if ( false !== $cached && is_array( $cached ) ) {
				$this->stats_cache = $cached;
				return $cached;
			}

			// Check transient cache (15 minute TTL for DB queries - optimized for performance)
			$transient_key = 'bbai_stats_v3';
			$cached        = get_transient( $transient_key );
			if ( false !== $cached && is_array( $cached ) ) {
				// Also populate object cache for next request
				wp_cache_set( $cache_key, $cached, $cache_group, 15 * MINUTE_IN_SECONDS );
				$this->stats_cache = $cached;
				return $cached;
			}

			global $wpdb;

			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'inherit' AND post_mime_type LIKE %s",
					'attachment',
					'image/%'
				)
			);

			$with_alt = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                 WHERE p.post_type = %s
                   AND p.post_status = %s
                   AND p.post_mime_type LIKE %s
                   AND m.meta_key = %s
                   AND TRIM(m.meta_value) <> ''",
					'attachment',
					'inherit',
					'image/%',
					'_wp_attachment_image_alt'
				)
			);

			$generated = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
					'_bbai_generated_at'
				)
			);

			$coverage = $total ? round( ( $with_alt / $total ) * 100, 1 ) : 0;
			$missing  = max( 0, $total - $with_alt );

			$opts  = get_option( self::OPTION_KEY, array() );
			$usage = $opts['usage'] ?? $this->default_usage();
			if ( ! empty( $usage['last_request'] ) ) {
				$date_format_raw                 = get_option( 'date_format' );
				$time_format_raw                 = get_option( 'time_format' );
				$date_format                     = is_string( $date_format_raw ) ? $date_format_raw : '';
				$time_format                     = is_string( $time_format_raw ) ? $time_format_raw : '';
				$format                          = ( ! empty( $date_format ) && ! empty( $time_format ) ) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';
				$usage['last_request_formatted'] = mysql2date( $format, $usage['last_request'] );
			}

			$latest_generated_raw = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_value DESC LIMIT 1",
					'_bbai_generated_at'
				)
			);
			$date_format_raw      = get_option( 'date_format' );
			$time_format_raw      = get_option( 'time_format' );
			$date_format          = is_string( $date_format_raw ) ? $date_format_raw : '';
			$time_format          = is_string( $time_format_raw ) ? $time_format_raw : '';
			$format               = ( ! empty( $date_format ) && ! empty( $time_format ) ) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';
			$latest_generated     = $latest_generated_raw ? mysql2date( $format, $latest_generated_raw ) : '';

			$top_source_row   = $wpdb->get_row(
				"SELECT meta_value AS source, COUNT(*) AS count
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = '_bbai_source' AND meta_value <> ''
                 GROUP BY meta_value
                 ORDER BY COUNT(*) DESC
                 LIMIT 1",
				ARRAY_A
			);
			$top_source_key   = sanitize_key( $top_source_row['source'] ?? '' );
			$top_source_count = intval( $top_source_row['count'] ?? 0 );

			$this->stats_cache = array(
				'total'                => $total,
				'with_alt'             => $with_alt,
				'missing'              => $missing,
				'generated'            => $generated,
				'coverage'             => $coverage,
				'usage'                => $usage,
				'token_limit'          => intval( $opts['token_limit'] ?? 0 ),
				'latest_generated'     => $latest_generated,
				'latest_generated_raw' => $latest_generated_raw,
				'top_source_key'       => $top_source_key,
				'top_source_count'     => $top_source_count,
				'dry_run_enabled'      => ! empty( $opts['dry_run'] ),
				'audit'                => $this->get_usage_rows( 10 ),
			);

			// Cache for 15 minutes (optimized - stats don't change frequently)
			wp_cache_set( $cache_key, $this->stats_cache, $cache_group, 15 * MINUTE_IN_SECONDS );
			set_transient( $transient_key, $this->stats_cache, 15 * MINUTE_IN_SECONDS );

			return $this->stats_cache;
		} catch ( \Exception $e ) {
			// If stats query fails, return empty stats array to prevent breaking REST responses
			// Silent failure - stats are non-critical
			return array(
				'total'        => 0,
				'with_alt'     => 0,
				'missing_alt'  => 0,
				'ai_generated' => 0,
				'manual'       => 0,
				'coverage'     => 0,
			);
		}
	}

	public function prepare_attachment_snapshot( $attachment_id ) {
		$attachment_id = intval( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return array();
		}

		$alt           = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$tokens        = intval( get_post_meta( $attachment_id, '_bbai_tokens_total', true ) );
		$prompt        = intval( get_post_meta( $attachment_id, '_bbai_tokens_prompt', true ) );
		$completion    = intval( get_post_meta( $attachment_id, '_bbai_tokens_completion', true ) );
		$generated_raw = get_post_meta( $attachment_id, '_bbai_generated_at', true );
		$generated     = $generated_raw ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $generated_raw ) : '';
		$source_key    = sanitize_key( get_post_meta( $attachment_id, '_bbai_source', true ) ?: 'unknown' );
		if ( ! $source_key ) {
			$source_key = 'unknown';
		}

		$analysis = $this->evaluate_alt_health( $attachment_id, $alt );

		return array(
			'id'                 => $attachment_id,
			'alt'                => $alt,
			'tokens'             => $tokens,
			'prompt'             => $prompt,
			'completion'         => $completion,
			'generated_raw'      => $generated_raw,
			'generated'          => $generated,
			'source_key'         => $source_key,
			'source_label'       => $this->format_source_label( $source_key ),
			'source_description' => $this->format_source_description( $source_key ),
			'score'              => $analysis['score'],
			'score_grade'        => $analysis['grade'],
			'score_status'       => $analysis['status'],
			'score_issues'       => $analysis['issues'],
			'score_summary'      => $analysis['review']['summary'] ?? '',
			'analysis'           => $analysis,
		);
	}

	private function hash_alt_text( string $alt ): string {
		$alt = strtolower( trim( (string) $alt ) );
		$alt = preg_replace( '/\s+/', ' ', $alt );
		return wp_hash( $alt );
	}

	private function purge_review_meta( int $attachment_id ): void {
		$keys = array(
			'_bbai_review_score',
			'_bbai_review_status',
			'_bbai_review_grade',
			'_bbai_review_summary',
			'_bbai_review_issues',
			'_bbai_review_model',
			'_bbai_reviewed_at',
			'_bbai_review_alt_hash',
		);
		foreach ( $keys as $key ) {
			delete_post_meta( $attachment_id, $key );
		}
	}

	private function get_review_snapshot( int $attachment_id, string $current_alt = '' ): ?array {
		$score = intval( get_post_meta( $attachment_id, '_bbai_review_score', true ) );
		if ( $score <= 0 ) {
			return null;
		}

		$stored_hash = get_post_meta( $attachment_id, '_bbai_review_alt_hash', true );
		if ( $current_alt !== '' ) {
			$current_hash = $this->hash_alt_text( $current_alt );
			if ( $stored_hash && ! hash_equals( $stored_hash, $current_hash ) ) {
				$this->purge_review_meta( $attachment_id );
				return null;
			}
		}

		$status      = sanitize_key( get_post_meta( $attachment_id, '_bbai_review_status', true ) );
		$grade_raw   = get_post_meta( $attachment_id, '_bbai_review_grade', true );
		$summary     = get_post_meta( $attachment_id, '_bbai_review_summary', true );
		$model       = get_post_meta( $attachment_id, '_bbai_review_model', true );
		$reviewed_at = get_post_meta( $attachment_id, '_bbai_reviewed_at', true );

		$issues_raw = get_post_meta( $attachment_id, '_bbai_review_issues', true );
		$issues     = array();
		if ( $issues_raw ) {
			$decoded = json_decode( $issues_raw, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $issue ) {
					if ( is_string( $issue ) ) {
						$issue = sanitize_text_field( $issue );
						if ( $issue !== '' ) {
							$issues[] = $issue;
						}
					}
				}
			}
		}

		return array(
			'score'        => max( 0, min( 100, $score ) ),
			'status'       => $status ?: null,
			'grade'        => is_string( $grade_raw ) ? sanitize_text_field( $grade_raw ) : null,
			'summary'      => is_string( $summary ) ? sanitize_text_field( $summary ) : '',
			'issues'       => $issues,
			'model'        => is_string( $model ) ? sanitize_text_field( $model ) : '',
			'reviewed_at'  => is_string( $reviewed_at ) ? $reviewed_at : '',
			'hash_present' => ! empty( $stored_hash ),
		);
	}

	private function evaluate_alt_health( int $attachment_id, string $alt ): array {
		$alt = trim( (string) $alt );
		if ( $alt === '' ) {
			return array(
				'score'     => 0,
				'grade'     => __( 'Missing', 'beepbeep-ai-alt-text-generator' ),
				'status'    => 'critical',
				'issues'    => array( __( 'ALT text is missing.', 'beepbeep-ai-alt-text-generator' ) ),
				'heuristic' => array(
					'score'  => 0,
					'grade'  => __( 'Missing', 'beepbeep-ai-alt-text-generator' ),
					'status' => 'critical',
					'issues' => array( __( 'ALT text is missing.', 'beepbeep-ai-alt-text-generator' ) ),
				),
				'review'    => null,
			);
		}

		$score  = 100;
		$issues = array();

		$normalized          = strtolower( trim( $alt ) );
		$placeholder_pattern = '/^(test|testing|sample|example|dummy|placeholder|alt(?:\s+text)?|image|photo|picture|n\/a|none|lorem)$/';
		if ( $normalized === '' || preg_match( $placeholder_pattern, $normalized ) ) {
			return array(
				'score'     => 0,
				'grade'     => __( 'Critical', 'beepbeep-ai-alt-text-generator' ),
				'status'    => 'critical',
				'issues'    => array( __( 'ALT text looks like placeholder content and must be rewritten.', 'beepbeep-ai-alt-text-generator' ) ),
				'heuristic' => array(
					'score'  => 0,
					'grade'  => __( 'Critical', 'beepbeep-ai-alt-text-generator' ),
					'status' => 'critical',
					'issues' => array( __( 'ALT text looks like placeholder content and must be rewritten.', 'beepbeep-ai-alt-text-generator' ) ),
				),
				'review'    => null,
			);
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $alt ) : strlen( $alt );
		if ( $length < 45 ) {
			$score   -= 35;
			$issues[] = __( 'Too short – add a richer description (45+ characters).', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $length > 160 ) {
			$score   -= 15;
			$issues[] = __( 'Very long – trim to keep the description concise (under 160 characters).', 'beepbeep-ai-alt-text-generator' );
		}

		if ( preg_match( '/\b(image|picture|photo|screenshot)\b/i', $alt ) ) {
			$score   -= 10;
			$issues[] = __( 'Contains generic filler words like “image” or “photo”.', 'beepbeep-ai-alt-text-generator' );
		}

		if ( preg_match( '/\b(test|testing|sample|example|dummy|placeholder|lorem|alt text)\b/i', $alt ) ) {
			$score    = min( $score - 80, 5 );
			$issues[] = __( 'Contains placeholder wording such as “test” or “sample”. Replace with a real description.', 'beepbeep-ai-alt-text-generator' );
		}

		$word_count = str_word_count( $alt, 0, '0123456789' );
		if ( $word_count < 4 ) {
			$score   -= 70;
			$score    = min( $score, 5 );
			$issues[] = __( 'ALT text is extremely brief – add meaningful descriptive words.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $word_count < 6 ) {
			$score   -= 50;
			$score    = min( $score, 20 );
			$issues[] = __( 'ALT text is too short to convey the subject in detail.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $word_count < 8 ) {
			$score   -= 35;
			$score    = min( $score, 40 );
			$issues[] = __( 'ALT text could use a few more descriptive words.', 'beepbeep-ai-alt-text-generator' );
		}

		if ( $score > 40 && $length < 30 ) {
			$score    = min( $score, 40 );
			$issues[] = __( 'Expand the description with one or two concrete details.', 'beepbeep-ai-alt-text-generator' );
		}

		$normalize = static function ( $value ) {
			$value = strtolower( (string) $value );
			$value = preg_replace( '/[^a-z0-9]+/i', ' ', $value );
			return trim( preg_replace( '/\s+/', ' ', $value ) );
		};

		$normalized_alt = $normalize( $alt );
		$title          = get_the_title( $attachment_id );
		if ( $title && $normalized_alt !== '' ) {
			$normalized_title = $normalize( $title );
			if ( $normalized_title !== '' && $normalized_alt === $normalized_title ) {
				$score   -= 12;
				$issues[] = __( 'Matches the attachment title – add more unique detail.', 'beepbeep-ai-alt-text-generator' );
			}
		}

		$file = get_attached_file( $attachment_id );
		if ( $file && $normalized_alt !== '' ) {
			$base            = pathinfo( $file, PATHINFO_FILENAME );
			$normalized_base = $normalize( $base );
			if ( $normalized_base !== '' && $normalized_alt === $normalized_base ) {
				$score   -= 20;
				$issues[] = __( 'Matches the file name – rewrite it to describe the image.', 'beepbeep-ai-alt-text-generator' );
			}
		}

		if ( ! preg_match( '/[a-z]{4,}/i', $alt ) ) {
			$score   -= 15;
			$issues[] = __( 'Lacks descriptive language – include meaningful nouns or adjectives.', 'beepbeep-ai-alt-text-generator' );
		}

		if ( ! preg_match( '/\b[a-z]/i', $alt ) ) {
			$score -= 20;
		}

		$score = max( 0, min( 100, $score ) );

		$status = $this->status_from_score( $score );
		$grade  = $this->grade_from_status( $status );

		if ( $status === 'review' && empty( $issues ) ) {
			$issues[] = __( 'Give this ALT another look to ensure it reflects the image details.', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $status === 'critical' && empty( $issues ) ) {
			$issues[] = __( 'ALT text should be rewritten for accessibility.', 'beepbeep-ai-alt-text-generator' );
		}

		$heuristic = array(
			'score'  => $score,
			'grade'  => $grade,
			'status' => $status,
			'issues' => array_values( array_unique( $issues ) ),
		);

		$review = $this->get_review_snapshot( $attachment_id, $alt );
		if ( $review && empty( $review['hash_present'] ) && $heuristic['score'] < $review['score'] ) {
			$review = null;
		}
		if ( $review ) {
			$final_score   = min( $heuristic['score'], $review['score'] );
			$review_status = $review['status'] ?: $this->status_from_score( $review['score'] );
			$final_status  = $this->worst_status( $heuristic['status'], $review_status );
			$final_grade   = $review['grade'] ?: $this->grade_from_status( $final_status );

			$combined_issues = array();
			if ( ! empty( $review['summary'] ) ) {
				$combined_issues[] = $review['summary'];
			}
			if ( ! empty( $review['issues'] ) ) {
				$combined_issues = array_merge( $combined_issues, $review['issues'] );
			}
			$combined_issues = array_merge( $combined_issues, $heuristic['issues'] );
			$combined_issues = array_values( array_unique( array_filter( $combined_issues ) ) );

			return array(
				'score'     => $final_score,
				'grade'     => $final_grade,
				'status'    => $final_status,
				'issues'    => $combined_issues,
				'heuristic' => $heuristic,
				'review'    => $review,
			);
		}

		return array(
			'score'     => $heuristic['score'],
			'grade'     => $heuristic['grade'],
			'status'    => $heuristic['status'],
			'issues'    => $heuristic['issues'],
			'heuristic' => $heuristic,
			'review'    => null,
		);
	}

	private function status_from_score( int $score ): string {
		if ( $score >= 90 ) {
			return 'great';
		}
		if ( $score >= 75 ) {
			return 'good';
		}
		if ( $score >= 60 ) {
			return 'review';
		}
		return 'critical';
	}

	private function grade_from_status( string $status ): string {
		switch ( $status ) {
			case 'great':
				return __( 'Excellent', 'beepbeep-ai-alt-text-generator' );
			case 'good':
				return __( 'Strong', 'beepbeep-ai-alt-text-generator' );
			case 'review':
				return __( 'Needs review', 'beepbeep-ai-alt-text-generator' );
			default:
				return __( 'Critical', 'beepbeep-ai-alt-text-generator' );
		}
	}

	private function worst_status( string $first, string $second ): string {
		$weights       = array(
			'great'    => 1,
			'good'     => 2,
			'review'   => 3,
			'critical' => 4,
		);
		$first_weight  = $weights[ $first ] ?? 2;
		$second_weight = $weights[ $second ] ?? 2;
		return $first_weight >= $second_weight ? $first : $second;
	}

	public function get_missing_attachment_ids( $limit = 5 ) {
		global $wpdb;
		$limit = intval( $limit );
		if ( $limit <= 0 ) {
			$limit = 5;
		}

		$sql = $wpdb->prepare(
			"SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m
               ON (p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt')
             WHERE p.post_type = %s
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE %s
               AND (m.meta_value IS NULL OR TRIM(m.meta_value) = '')
             ORDER BY p.ID DESC
             LIMIT %d",
			'attachment',
			'image/%',
			$limit
		);

		return array_map( 'intval', (array) $wpdb->get_col( $sql ) );
	}

	public function get_all_attachment_ids( $limit = 5, $offset = 0 ) {
		global $wpdb;
		$limit  = max( 1, intval( $limit ) );
		$offset = max( 0, intval( $offset ) );

		$sql = $wpdb->prepare(
			"SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} gen ON gen.post_id = p.ID AND gen.meta_key = '_bbai_generated_at'
             WHERE p.post_type = %s
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE %s
             ORDER BY
                 CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC,
                 p.ID DESC
             LIMIT %d OFFSET %d",
			'attachment',
			'image/%',
			$limit,
			$offset
		);

		$rows = $wpdb->get_col( $sql );
		return array_map( 'intval', (array) $rows );
	}

	private function get_usage_rows( $limit = 10, $include_all = false ) {
		global $wpdb;
		$limit = max( 1, intval( $limit ) );

		$cache_key = 'bbai_usage_rows_' . md5( $limit . '|' . ( $include_all ? 'all' : 'slice' ) );
		if ( ! $include_all ) {
			$cached = wp_cache_get( $cache_key, 'bbai' );
			if ( $cached !== false ) {
				return $cached;
			}
		}
		$base_query = "SELECT p.ID,
                       tokens.meta_value AS tokens_total,
                       prompt.meta_value AS tokens_prompt,
                       completion.meta_value AS tokens_completion,
                       alt.meta_value AS alt_text,
                       src.meta_value AS source,
                       model.meta_value AS model,
                       gen.meta_value AS generated_at
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} tokens ON tokens.post_id = p.ID AND tokens.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} prompt ON prompt.post_id = p.ID AND prompt.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} completion ON completion.post_id = p.ID AND completion.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = p.ID AND alt.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} model ON model.post_id = p.ID AND model.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} gen ON gen.post_id = p.ID AND gen.meta_key = %s
                WHERE p.post_type = %s AND p.post_mime_type LIKE %s
                ORDER BY
                    CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC,
                    CAST(tokens.meta_value AS UNSIGNED) DESC";

		$prepare_params = array(
			'_bbai_tokens_total',
			'_bbai_tokens_prompt',
			'_bbai_tokens_completion',
			'_wp_attachment_image_alt',
			'_bbai_source',
			'_bbai_model',
			'_bbai_generated_at',
			'attachment',
			'image/%',
		);

		if ( ! $include_all ) {
			$base_query      .= ' LIMIT %d';
			$prepare_params[] = $limit;
		}

		$sql  = $wpdb->prepare( $base_query, ...$prepare_params );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			if ( ! $include_all ) {
				wp_cache_set( $cache_key, array(), 'bbai', MINUTE_IN_SECONDS * 2 );
			}
			return array();
		}

		$formatted = array_map(
			function ( $row ) {
				$generated = $row['generated_at'] ?? '';
				if ( $generated ) {
					$generated = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $generated );
				}

				$source = sanitize_key( $row['source'] ?? 'unknown' );
				if ( ! $source ) {
					$source = 'unknown'; }

				$thumb = wp_get_attachment_image_src( $row['ID'], 'thumbnail' );

				return array(
					'id'                 => intval( $row['ID'] ),
					'title'              => get_the_title( $row['ID'] ),
					'alt'                => $row['alt_text'] ?? '',
					'tokens'             => intval( $row['tokens_total'] ?? 0 ),
					'prompt'             => intval( $row['tokens_prompt'] ?? 0 ),
					'completion'         => intval( $row['tokens_completion'] ?? 0 ),
					'source'             => $source,
					'source_label'       => $this->format_source_label( $source ),
					'source_description' => $this->format_source_description( $source ),
					'model'              => $row['model'] ?? '',
					'generated'          => $generated,
					'thumb'              => $thumb ? $thumb[0] : '',
					'details_url'        => add_query_arg( 'item', $row['ID'], admin_url( 'upload.php' ) ) . '#attachment_alt',
					'view_url'           => get_attachment_link( $row['ID'] ),
				);
			},
			$rows
		);

		if ( ! $include_all ) {
			wp_cache_set( $cache_key, $formatted, 'bbai', MINUTE_IN_SECONDS * 5 );
		}

		return $formatted;
	}

	private function get_source_meta_map() {
		return array(
			'auto'      => array(
				'label'       => __( 'Auto (upload)', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Generated automatically when the image was uploaded.', 'beepbeep-ai-alt-text-generator' ),
			),
			'ajax'      => array(
				'label'       => __( 'Media Library (single)', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Triggered from the Media Library row action or attachment details screen.', 'beepbeep-ai-alt-text-generator' ),
			),
			'bulk'      => array(
				'label'       => __( 'Media Library (bulk)', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Generated via the Media Library bulk action.', 'beepbeep-ai-alt-text-generator' ),
			),
			'dashboard' => array(
				'label'       => __( 'Dashboard quick actions', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Generated from the dashboard buttons.', 'beepbeep-ai-alt-text-generator' ),
			),
			'wpcli'     => array(
				'label'       => __( 'WP-CLI', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Generated via the wp ai-alt CLI command.', 'beepbeep-ai-alt-text-generator' ),
			),
			'manual'    => array(
				'label'       => __( 'Manual / custom', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Generated by custom code or integration.', 'beepbeep-ai-alt-text-generator' ),
			),
			'unknown'   => array(
				'label'       => __( 'Unknown', 'beepbeep-ai-alt-text-generator' ),
				'description' => __( 'Source not recorded for this ALT text.', 'beepbeep-ai-alt-text-generator' ),
			),
		);
	}

	private function format_source_label( $key ) {
		$map = $this->get_source_meta_map();
		$key = sanitize_key( $key ?: 'unknown' );
		return $map[ $key ]['label'] ?? $map['unknown']['label'];
	}

	private function format_source_description( $key ) {
		$map = $this->get_source_meta_map();
		$key = sanitize_key( $key ?: 'unknown' );
		return $map[ $key ]['description'] ?? $map['unknown']['description'];
	}

	public function handle_usage_export() {
		if ( ! $this->user_can_manage() ) {
			wp_die( __( 'You do not have permission to export usage data.', 'beepbeep-ai-alt-text-generator' ) );
		}
		check_admin_referer( 'bbai_usage_export' );

		$rows     = $this->get_usage_rows( 10, true );
		$filename = 'bbai-usage-' . gmdate( 'Ymd-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Attachment ID', 'Title', 'ALT Text', 'Source', 'Model', 'Generated At' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row['id'],
					$row['title'],
					$row['alt'],
					$row['source'],
					$row['tokens'],
					$row['prompt'],
					$row['completion'],
					$row['model'],
					'',
				)
			);
		}
		fclose( $output );
		exit;
	}

	public function handle_debug_log_export() {
		if ( ! $this->user_can_manage() ) {
			wp_die( __( 'You do not have permission to export debug logs.', 'beepbeep-ai-alt-text-generator' ) );
		}
		check_admin_referer( 'bbai_debug_export' );

		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			wp_die( __( 'Debug logging is not available.', 'beepbeep-ai-alt-text-generator' ) );
		}

		global $wpdb;
		$table = Debug_Log::table();
		// Table name is validated by the class, but ensure it's safe
		$table_escaped = esc_sql( $table );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped by class method
		$rows = $wpdb->get_results( "SELECT * FROM `{$table_escaped}` ORDER BY created_at DESC", ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bbai-debug-logs-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Timestamp', 'Level', 'Message', 'Source', 'Context' ) );
		foreach ( $rows as $row ) {
			$context = $row['context'] ?? '';
			fputcsv(
				$output,
				array(
					$row['created_at'],
					$row['level'],
					$row['message'],
					$row['source'],
					$context,
				)
			);
		}
		fclose( $output );
		exit;
	}

	private function redact_api_token( $message ) {
		if ( ! is_string( $message ) || $message === '' ) {
			return $message;
		}

		$mask = function ( $token ) {
			$len = strlen( $token );
			if ( $len <= 8 ) {
				return str_repeat( '*', $len );
			}
			return substr( $token, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $token, -4 );
		};

		$message = preg_replace_callback(
			'/(Incorrect API key provided:\s*)(\S+)/i',
			function ( $matches ) use ( $mask ) {
				return $matches[1] . $mask( $matches[2] );
			},
			$message
		);

		$message = preg_replace_callback(
			'/(sk-[A-Za-z0-9]{4})([A-Za-z0-9]{10,})([A-Za-z0-9]{4})/i',
			function ( $matches ) {
				return $matches[1] . str_repeat( '*', strlen( $matches[2] ) ) . $matches[3];
			},
			$message
		);

		return $message;
	}

	private function extract_json_object( string $content ) {
		$content = trim( $content );
		if ( $content === '' ) {
			return null;
		}

		if ( stripos( $content, '```' ) !== false ) {
			$content = preg_replace( '/```json/i', '', $content );
			$content = str_replace( '```', '', $content );
			$content = trim( $content );
		}

		if ( $content !== '' && is_string( $content ) && isset( $content[0] ) && $content[0] !== '{' ) {
			$start = strpos( (string) $content, '{' );
			$end   = strrpos( (string) $content, '}' );
			if ( $start !== false && $end !== false && $end > $start ) {
				$content = substr( $content, $start, $end - $start + 1 );
			}
		}

		$decoded = json_decode( $content, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		return null;
	}

	private function should_retry_without_image( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		if ( $error->get_error_code() !== 'api_error' ) {
			return false;
		}

		$error_message = $error->get_error_message();
		if ( ! is_string( $error_message ) || empty( $error_message ) ) {
			return false;
		}
		$message = strtolower( $error_message );
		$needles = array( 'error while downloading', 'failed to download', 'unsupported image url' );
		foreach ( $needles as $needle ) {
			if ( is_string( $message ) && strpos( (string) $message, (string) $needle ) !== false ) {
				return true;
			}
		}

		$data = $error->get_error_data();
		if ( is_array( $data ) ) {
			if ( ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
				$msg = strtolower( $data['message'] );
				foreach ( $needles as $needle ) {
					if ( is_string( $msg ) && strpos( (string) $msg, (string) $needle ) !== false ) {
						return true;
					}
				}
			}
			if ( ! empty( $data['body']['error']['message'] ) && is_string( $data['body']['error']['message'] ) ) {
				$msg = strtolower( $data['body']['error']['message'] );
				foreach ( $needles as $needle ) {
					if ( is_string( $msg ) && strpos( (string) $msg, (string) $needle ) !== false ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	private function build_inline_image_payload( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new \WP_Error( 'inline_image_missing', __( 'Unable to locate the image file for inline embedding.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$size = filesize( $file );
		if ( $size === false || $size <= 0 ) {
			return new \WP_Error( 'inline_image_size', __( 'Unable to read the image size for inline embedding.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$limit = apply_filters( 'bbai_inline_image_limit', 1024 * 1024 * 2, $attachment_id, $file );
		if ( $size > $limit ) {
			return new \WP_Error(
				'inline_image_too_large',
				__( 'Image exceeds the inline embedding size limit.', 'beepbeep-ai-alt-text-generator' ),
				array(
					'size'  => $size,
					'limit' => $limit,
				)
			);
		}

		$contents = file_get_contents( $file );
		if ( $contents === false ) {
			return new \WP_Error( 'inline_image_read_failed', __( 'Unable to read the image file for inline embedding.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( empty( $mime ) ) {
			$mime = function_exists( 'mime_content_type' ) ? mime_content_type( $file ) : 'image/jpeg';
		}

		$base64 = base64_encode( $contents );
		if ( ! $base64 ) {
			return new \WP_Error( 'inline_image_encode_failed', __( 'Failed to encode the image for inline embedding.', 'beepbeep-ai-alt-text-generator' ) );
		}

		unset( $contents );

		return array(
			'payload' => array(
				'type'      => 'image_url',
				'image_url' => array(
					'url' => 'data:' . $mime . ';base64,' . $base64,
				),
			),
		);
	}

	private function review_alt_text_with_model( int $attachment_id, string $alt, string $image_strategy, $image_payload_used, array $opts, string $api_key ) {
		$alt = trim( (string) $alt );
		if ( $alt === '' ) {
			return new \WP_Error( 'review_skipped', __( 'ALT text is empty; skipped review.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$review_model = $opts['review_model'] ?? ( $opts['model'] ?? 'gpt-4o-mini' );
		$review_model = apply_filters( 'bbai_review_model', $review_model, $attachment_id, $opts );
		if ( ! $review_model ) {
			return new \WP_Error( 'review_model_missing', __( 'No review model configured.', 'beepbeep-ai-alt-text-generator' ) );
		}

		$image_payload = $image_payload_used;
		if ( ! $image_payload ) {
			if ( $image_strategy === 'inline' ) {
				$inline = $this->build_inline_image_payload( $attachment_id );
				if ( ! is_wp_error( $inline ) ) {
					$image_payload = $inline['payload'];
				}
			} else {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$image_payload = array(
						'type'      => 'image_url',
						'image_url' => array(
							'url' => $url,
						),
					);
				}
			}
		}

		$title     = get_the_title( $attachment_id );
		$file_path = get_attached_file( $attachment_id );
		$filename  = $file_path ? wp_basename( $file_path ) : '';

		$context_lines = array();
		if ( $title ) {
			$context_lines[] = sprintf( __( 'Media title: %s', 'beepbeep-ai-alt-text-generator' ), $title );
		}
		if ( $filename ) {
			$context_lines[] = sprintf( __( 'Filename: %s', 'beepbeep-ai-alt-text-generator' ), $filename );
		}

		$quoted_alt = str_replace( '"', '\"', (string) ( $alt ?? '' ) );

		$instructions = 'You are an accessibility QA assistant. Review the provided ALT text for the accompanying image. '
			. 'Flag hallucinated details, inaccurate descriptions, missing primary subjects, demographic assumptions, or awkward phrasing. '
			. 'Confirm the sentence mentions the main subject and at least one visible attribute such as colour, texture, motion, or background context. '
			. 'Score strictly: reward ALT text only when it accurately and concisely describes the image. '
			. 'If the ALT text contains placeholder wording (for example ‘test’, ‘sample’, ‘dummy text’, ‘image’, ‘photo’) anywhere in the sentence, or omits the primary subject, score it 10 or lower. '
			. 'Extremely short descriptions (fewer than six words) should rarely exceed a score of 30.';

		$text_block = $instructions . "\n\n"
			. 'ALT text candidate: "' . $quoted_alt . "\"\n";

		if ( $context_lines ) {
			$text_block .= implode( "\n", $context_lines ) . "\n";
		}

		$text_block .= "\nReturn valid JSON with keys: "
			. 'score (integer 0-100), verdict (excellent, good, review, or critical), '
			. 'summary (short sentence), and issues (array of short strings). '
			. 'Do not include any additional keys or explanatory prose.';

		$user_content = array(
			array(
				'type' => 'text',
				'text' => $text_block,
			),
		);

		if ( $image_payload ) {
			$user_content[] = $image_payload;
		}

		$body = array(
			'model'       => $review_model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => 'You are an impartial accessibility QA reviewer. Always return strict JSON and be conservative when scoring.',
				),
				array(
					'role'    => 'user',
					'content' => $user_content,
				),
			),
			'temperature' => 0.1,
			'max_tokens'  => 280,
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 45,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );

		if ( $code >= 300 || empty( $data['choices'][0]['message']['content'] ) ) {
			$api_message = isset( $data['error']['message'] ) ? $data['error']['message'] : ( $raw_body ?: 'OpenAI review failed.' );
			$api_message = $this->redact_api_token( $api_message );
			return new \WP_Error(
				'review_api_error',
				$api_message,
				array(
					'status' => $code,
					'body'   => $data,
				)
			);
		}

		$content = $data['choices'][0]['message']['content'];
		$parsed  = $this->extract_json_object( $content );
		if ( ! $parsed ) {
			return new \WP_Error( 'review_parse_failed', __( 'Unable to parse review response.', 'beepbeep-ai-alt-text-generator' ), array( 'response' => $content ) );
		}

		$score = isset( $parsed['score'] ) ? intval( $parsed['score'] ) : 0;
		$score = max( 0, min( 100, $score ) );

		$verdict    = isset( $parsed['verdict'] ) ? strtolower( trim( (string) $parsed['verdict'] ) ) : '';
		$status_map = array(
			'excellent'    => 'great',
			'great'        => 'great',
			'good'         => 'good',
			'strong'       => 'good',
			'review'       => 'review',
			'needs review' => 'review',
			'warning'      => 'review',
			'critical'     => 'critical',
			'fail'         => 'critical',
			'poor'         => 'critical',
		);
		$status     = $status_map[ $verdict ] ?? null;
		if ( ! $status ) {
			$status = $this->status_from_score( $score );
		}

		$summary = isset( $parsed['summary'] ) ? sanitize_text_field( $parsed['summary'] ) : '';
		if ( ! $summary && isset( $parsed['justification'] ) ) {
			$summary = sanitize_text_field( $parsed['justification'] );
		}

		$issues = array();
		if ( ! empty( $parsed['issues'] ) && is_array( $parsed['issues'] ) ) {
			foreach ( $parsed['issues'] as $issue ) {
				$issue = sanitize_text_field( $issue );
				if ( $issue !== '' ) {
					$issues[] = $issue;
				}
			}
		}

		$issues = array_values( array_unique( $issues ) );

		$usage_summary = array(
			'prompt'     => intval( $data['usage']['prompt_tokens'] ?? 0 ),
			'completion' => intval( $data['usage']['completion_tokens'] ?? 0 ),
			'total'      => intval( $data['usage']['total_tokens'] ?? 0 ),
		);

		return array(
			'score'   => $score,
			'status'  => $status,
			'grade'   => $this->grade_from_status( $status ),
			'summary' => $summary,
			'issues'  => $issues,
			'model'   => $review_model,
			'usage'   => $usage_summary,
			'verdict' => $verdict,
		);
	}

	public function persist_generation_result( int $attachment_id, string $alt, array $usage_summary, string $source, string $model, string $image_strategy, $review_result ): void {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags( $alt ) );
		update_post_meta( $attachment_id, '_bbai_source', $source );
		update_post_meta( $attachment_id, '_bbai_model', $model );
		update_post_meta( $attachment_id, '_bbai_generated_at', current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_bbai_tokens_prompt', $usage_summary['prompt'] );
		update_post_meta( $attachment_id, '_bbai_tokens_completion', $usage_summary['completion'] );
		update_post_meta( $attachment_id, '_bbai_tokens_total', $usage_summary['total'] );

		if ( $image_strategy === 'remote' ) {
			delete_post_meta( $attachment_id, '_bbai_image_reference' );
		} else {
			update_post_meta( $attachment_id, '_bbai_image_reference', $image_strategy );
		}

		if ( ! is_wp_error( $review_result ) ) {
			update_post_meta( $attachment_id, '_bbai_review_score', $review_result['score'] );
			update_post_meta( $attachment_id, '_bbai_review_status', $review_result['status'] );
			update_post_meta( $attachment_id, '_bbai_review_grade', $review_result['grade'] );
			update_post_meta( $attachment_id, '_bbai_review_summary', $review_result['summary'] );
			update_post_meta( $attachment_id, '_bbai_review_issues', wp_json_encode( $review_result['issues'] ) );
			update_post_meta( $attachment_id, '_bbai_review_model', $review_result['model'] );
			update_post_meta( $attachment_id, '_bbai_reviewed_at', current_time( 'mysql' ) );
			update_post_meta( $attachment_id, '_bbai_review_alt_hash', $this->hash_alt_text( $alt ) );
			delete_post_meta( $attachment_id, '_bbai_review_error' );
			if ( ! empty( $review_result['usage'] ) ) {
				$this->record_usage( $review_result['usage'] );
			}
		} else {
			update_post_meta( $attachment_id, '_bbai_review_error', $review_result->get_error_message() );
		}

		// Invalidate stats cache after persisting all generation data
		$this->invalidate_stats_cache();
	}

	public function maybe_generate_on_upload( $attachment_id ) {
		$opts = get_option( self::OPTION_KEY, array() );
		// Default to enabled if option not explicitly disabled
		if ( array_key_exists( 'enable_on_upload', $opts ) && empty( $opts['enable_on_upload'] ) ) {
			return;
		}
		if ( ! $this->is_image( $attachment_id ) ) {
			return;
		}
		$this->invalidate_stats_cache();
		$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( $existing && empty( $opts['force_overwrite'] ) ) {
			return;
		}
		// Respect monthly limit and surface upgrade prompt as admin notice
		if ( $this->api_client->has_reached_limit() ) {
			set_transient( 'beepbeepai_limit_notice', 1, MINUTE_IN_SECONDS * 10 );
			return;
		}
		$this->generate_and_save( $attachment_id, 'auto' );
	}

	public function generate_and_save( $attachment_id, $source = 'manual', int $retry_count = 0, array $feedback = array(), $regenerate = false ) {
		$opts = get_option( self::OPTION_KEY, array() );

		// Allocate free credits on first generation request (one-time per site)
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		\BeepBeepAI\AltTextGenerator\Usage_Tracker::allocate_free_credits_if_needed();

		// Capture current user ID for credit tracking
		// Use 0 for anonymous/system users (auto-upload, queue processing, etc.)
		$user_id = get_current_user_id();

		// For AJAX/REST calls, try to get user from nonce/authentication
		if ( $user_id <= 0 && ( $source === 'ajax' || $source === 'inline' || $source === 'manual' ) ) {
			// Check if we're in a REST API context
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				// REST API should have authenticated user via cookie/nonce
				$user_id = get_current_user_id();
			}
			// For AJAX calls, get_current_user_id() should work if user is logged in
			// If still 0, it means user is not authenticated
		}

		// Set to 0 for system/automated operations only
		if ( $user_id <= 0 || $source === 'auto' || $source === 'queue' || $source === 'wpcli' ) {
			$user_id = 0; // Track as "System" for anonymous/automated operations
		}

		// Skip authentication check in local development mode
		$has_license = $this->api_client->has_active_license();
		if ( ! $has_license && ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV ) ) {
			// Check site-wide quota before generation
			// Wrap in try-catch to prevent PHP errors from breaking REST responses
			// Use has_reached_limit() instead of Token_Quota_Service for consistency
			// has_reached_limit() already includes proper cache checking and fallback logic
			// This prevents false "quota exhausted" errors from stale cache data
			try {
				if ( $this->api_client->has_reached_limit() ) {
					// Get usage data for the error response
					$usage = $this->api_client->get_usage();
					if ( is_wp_error( $usage ) ) {
						// Fall back to cached usage for display
						require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
						$usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );
					}

					return new \WP_Error(
						'limit_reached',
						__( 'Monthly quota exhausted. Upgrade to Pro to continue generating alt text.', 'beepbeep-ai-alt-text-generator' ),
						array(
							'code'  => 'quota_exhausted',
							'usage' => is_array( $usage ) ? $usage : null,
						)
					);
				}
			} catch ( \Exception $e ) {
				// If quota check fails due to error, don't block generation
				// Backend will handle usage limits
				// Silent failure - generation will proceed
			}
		}

		if ( ! $this->is_image( $attachment_id ) ) {
			return new \WP_Error( 'not_image', 'Attachment is not an image.' );
		}

		// Prefer higher-quality default for better accuracy
		$model        = apply_filters( 'bbai_model', $opts['model'] ?? 'gpt-4o', $attachment_id, $opts );
		$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$prompt       = $this->build_prompt( $attachment_id, $opts, $existing_alt, $retry_count > 0, $feedback );

		if ( ! empty( $opts['dry_run'] ) ) {
			update_post_meta( $attachment_id, '_bbai_last_prompt', $prompt );
			update_post_meta( $attachment_id, '_bbai_source', 'dry-run' );
			update_post_meta( $attachment_id, '_bbai_model', $model );
			update_post_meta( $attachment_id, '_bbai_generated_at', current_time( 'mysql' ) );
			$this->stats_cache = null;
			return new \WP_Error( 'bbai_dry_run', __( 'Dry run enabled. Prompt stored for review; ALT text not updated.', 'beepbeep-ai-alt-text-generator' ), array( 'prompt' => $prompt ) );
		}

		// Build context for API
		$post      = get_post( $attachment_id );
		$file_path = get_attached_file( $attachment_id );
		$filename  = $file_path ? basename( $file_path ) : '';
		$title     = get_the_title( $attachment_id );
		$context   = array(
			'filename'   => $filename,
			'title'      => is_string( $title ) ? $title : '',
			'caption'    => $post && isset( $post->post_excerpt ) ? (string) $post->post_excerpt : '',
			'post_title' => '',
		);

		// Get parent post context if available
		if ( $post && $post->post_parent ) {
			$parent = get_post( $post->post_parent );
			if ( $parent && isset( $parent->post_title ) ) {
				$context['post_title'] = is_string( $parent->post_title ) ? $parent->post_title : '';
			}
		}

		// Always call the real API to generate actual alt text
		// (Mock mode disabled - we want real AI-generated descriptions)
		$api_response = $this->api_client->generate_alt_text( $attachment_id, $context, $regenerate );

		if ( is_wp_error( $api_response ) ) {
			$error_code    = $api_response->get_error_code();
			$error_message = $api_response->get_error_message();
			$error_data    = $api_response->get_error_data();

			// Check if this is a quota/limit error - verify against cached usage
			// The backend API might incorrectly report quota exhausted when credits are available
			$error_message_lower = strtolower( $error_message );
			$is_quota_error      = ( $error_code === 'limit_reached' || $error_code === 'quota_exhausted' || $error_code === 'quota_check_mismatch' ||
							strpos( $error_message_lower, 'quota exhausted' ) !== false ||
							strpos( $error_message_lower, 'monthly limit' ) !== false ||
							strpos( $error_message_lower, 'monthly quota' ) !== false ||
							( is_array( $error_data ) && isset( $error_data['status_code'] ) && intval( $error_data['status_code'] ) === 429 ) );

			// If it's a quota_check_mismatch error (from API client cache check), allow retry
			if ( $error_code === 'quota_check_mismatch' ) {
				// This is from our cache validation - suggest retry but still return the error
				// The frontend should handle retry based on the error code
			} elseif ( $is_quota_error ) {
				// Check cached usage before accepting the backend's quota error
				require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
				$cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );

				if ( is_array( $cached_usage ) && isset( $cached_usage['remaining'] ) && is_numeric( $cached_usage['remaining'] ) && $cached_usage['remaining'] > 0 ) {
					// Cached usage shows credits available - backend error might be incorrect
					// Clear cache and do a fresh check to see actual status
					\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
					$fresh_usage = $this->api_client->get_usage();

					if ( ! is_wp_error( $fresh_usage ) && is_array( $fresh_usage ) && isset( $fresh_usage['remaining'] ) && is_numeric( $fresh_usage['remaining'] ) && $fresh_usage['remaining'] > 0 ) {
						// Fresh API check shows credits available - backend quota error was wrong
						// Update cache and return a retry error instead of blocking
						\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $fresh_usage );

						if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
							Debug_Log::log(
								'warning',
								'Backend reported quota exhausted but cache and fresh API check show credits available',
								array(
									'attachment_id'    => $attachment_id,
									'cached_remaining' => $cached_usage['remaining'],
									'api_remaining'    => $fresh_usage['remaining'],
									'backend_error'    => $error_message,
									'error_code'       => $error_code,
								),
								'generation'
							);
						}

						// Return a retry error instead of blocking
						return new \WP_Error(
							'quota_check_mismatch',
							__( 'Backend reported quota limit, but credits appear available. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' ),
							array(
								'code'        => 'quota_check_mismatch',
								'retry_after' => 3,
								'usage'       => $fresh_usage,
							)
						);
					}
				}
			}

			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				// Get error data for detailed logging
				// Sanitize error message to prevent exposing sensitive API information
				$sanitized_message = $this->sanitize_error_message( $error_message );

				// Build detailed context for logging
				$log_context = array(
					'attachment_id' => $attachment_id,
					'code'          => $error_code,
					'message'       => $sanitized_message,
				);

				// Include additional error data if available (but sanitize it)
				if ( is_array( $error_data ) ) {
					if ( isset( $error_data['status_code'] ) ) {
						$log_context['status_code'] = $error_data['status_code'];
					}
					if ( isset( $error_data['api_response'] ) && is_array( $error_data['api_response'] ) ) {
						// Include API response details but sanitize sensitive fields
						$api_resp                         = $error_data['api_response'];
						$log_context['api_error_code']    = $api_resp['code'] ?? null;
						$log_context['api_error_message'] = isset( $api_resp['message'] ) ? $this->sanitize_error_message( $api_resp['message'] ) : null;
					}
				}

				Debug_Log::log(
					'error',
					'Alt text generation failed',
					$log_context,
					'generation'
				);
			}
			return $api_response;
		}

		// The api_response is $response['data'] from generate_alt_text()
		// If generate_alt_text() returns WP_Error, it's already handled above
		// So at this point, api_response should be the data object
		// Validate that alt_text exists in the response
		if ( ! isset( $api_response['alt_text'] ) || empty( $api_response['alt_text'] ) ) {
			$error_message = __( 'Backend API returned response but no alt text was generated.', 'beepbeep-ai-alt-text-generator' );

			// Log this error with full response structure for debugging
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				Debug_Log::log(
					'error',
					'Alt text generation failed - missing alt_text in response',
					array(
						'attachment_id'    => $attachment_id,
						'response_keys'    => is_array( $api_response ) ? array_keys( $api_response ) : 'not array',
						'response_type'    => gettype( $api_response ),
						'has_usage'        => isset( $api_response['usage'] ),
						'has_tokens'       => isset( $api_response['tokens'] ),
						'response_preview' => is_array( $api_response ) ? wp_json_encode( array_slice( $api_response, 0, 5 ) ) : 'not array',
					),
					'generation'
				);
			}

			// DO NOT update usage or log credits if alt_text is missing
			// The backend may have consumed credits, but we shouldn't record it as successful usage
			return new \WP_Error( 'missing_alt_text', $error_message, array( 'code' => 'api_response_invalid' ) );
		}

		// Refresh license usage data when backend returns updated organization details
		if ( $has_license && ! empty( $api_response['organization'] ) ) {
			$existing_license                = $this->api_client->get_license_data() ?? array();
			$updated_license                 = $existing_license;
			$updated_license['organization'] = array_merge(
				$existing_license['organization'] ?? array(),
				$api_response['organization']
			);
			if ( ! empty( $api_response['site'] ) ) {
				$updated_license['site'] = $api_response['site'];
			}
			$updated_license['updated_at'] = current_time( 'mysql' );
			$this->api_client->set_license_data( $updated_license );
			Usage_Tracker::clear_cache();
		}

		// CRITICAL: Only update usage AFTER we've confirmed alt_text exists
		// This ensures we NEVER log credits for failed generations, even if backend consumed them
		// The backend may have consumed credits when calling OpenAI, but if alt_text is missing,
		// we should NOT record it as successful usage locally
		if ( ! empty( $api_response['usage'] ) && is_array( $api_response['usage'] ) ) {
			Usage_Tracker::update_usage( $api_response['usage'] );

			if ( $has_license ) {
				$existing_license = $this->api_client->get_license_data() ?? array();
				$updated_license  = $existing_license ?: array();
				$organization     = $updated_license['organization'] ?? array();

				if ( isset( $api_response['usage']['limit'] ) ) {
					$organization['tokenLimit'] = intval( $api_response['usage']['limit'] );
				}

				if ( isset( $api_response['usage']['remaining'] ) ) {
					$organization['tokensRemaining'] = max( 0, intval( $api_response['usage']['remaining'] ) );
				} elseif ( isset( $api_response['usage']['used'] ) && isset( $organization['tokenLimit'] ) ) {
					$organization['tokensRemaining'] = max( 0, intval( $organization['tokenLimit'] ) - intval( $api_response['usage']['used'] ) );
				}

				if ( ! empty( $api_response['usage']['resetDate'] ) ) {
					$organization['resetDate'] = sanitize_text_field( $api_response['usage']['resetDate'] );
				} elseif ( ! empty( $api_response['usage']['nextReset'] ) ) {
					$organization['resetDate'] = sanitize_text_field( $api_response['usage']['nextReset'] );
				}

				if ( ! empty( $api_response['usage']['plan'] ) ) {
					$organization['plan'] = sanitize_key( $api_response['usage']['plan'] );
				}

				$updated_license['organization'] = $organization;
				$updated_license['updated_at']   = current_time( 'mysql' );
				$this->api_client->set_license_data( $updated_license );
				Usage_Tracker::clear_cache();
			}
		} else {
			// Backend didn't return usage in response - refresh from API to get updated credit count
			// BUT only do this AFTER we've confirmed alt_text exists (which we have at this point)
			// This ensures credits are properly reflected even if backend doesn't include usage in response
			// CRITICAL: Only refresh usage AFTER successful generation (alt_text exists)
			// If backend deducted credits on failed attempts, we don't want to record those here
			Usage_Tracker::clear_cache();
			$updated_usage = $this->api_client->get_usage();
			if ( ! is_wp_error( $updated_usage ) && is_array( $updated_usage ) && ! empty( $updated_usage ) ) {
				// Only update if we have a valid alt_text (successful generation)
				// This check is redundant since we already returned if alt_text is missing above,
				// but it's a safety check to ensure we never record usage for failed generations
				Usage_Tracker::update_usage( $updated_usage );
			}
		}

		$alt           = trim( $api_response['alt_text'] );
		$usage_summary = $api_response['tokens'] ?? array(
			'prompt'     => 0,
			'completion' => 0,
			'total'      => 0,
		);

		$result = array(
			'alt'   => $alt,
			'usage' => array(
				'prompt'     => intval( $usage_summary['prompt_tokens'] ?? 0 ),
				'completion' => intval( $usage_summary['completion_tokens'] ?? 0 ),
				'total'      => intval( $usage_summary['total_tokens'] ?? 0 ),
			),
		);

		$image_strategy = 'api-proxy';

		$review_result = null;
		if ( ! empty( $api_response['review'] ) && is_array( $api_response['review'] ) ) {
			$review = $api_response['review'];
			$issues = array();
			if ( ! empty( $review['issues'] ) && is_array( $review['issues'] ) ) {
				foreach ( $review['issues'] as $issue ) {
					if ( is_string( $issue ) && $issue !== '' ) {
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

		// Check if generated alt is same as existing (unlikely but possible)
		// Skip this check when regenerating - user explicitly wants to regenerate
		if ( ! is_wp_error( $result ) && $existing_alt && ! $regenerate ) {
			$generated = trim( $result['alt'] );
			if ( strcasecmp( $generated, trim( $existing_alt ) ) === 0 ) {
				$result = new \WP_Error(
					'duplicate_alt',
					__( 'Generated ALT text matched the existing value.', 'beepbeep-ai-alt-text-generator' ),
					array(
						'existing'  => $existing_alt,
						'generated' => $generated,
					)
				);
			}
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$usage_summary = $result['usage'];
		$alt           = $result['alt'];

		// Log credit usage for this generation
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
		$credits_used = isset( $usage_summary['total_tokens'] ) ? intval( $usage_summary['total_tokens'] ) : 1;
		$token_cost   = isset( $usage_summary['cost'] ) ? floatval( $usage_summary['cost'] ) : null;
		$model_used   = isset( $usage_summary['model'] ) ? sanitize_text_field( $usage_summary['model'] ) : $model;
		\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::log_usage(
			$attachment_id,
			$user_id,
			$credits_used,
			$token_cost,
			$model_used,
			$source
		);

		// Log usage event for multi-user visualization
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';
		$action_type = $regenerate ? 'regenerate' : ( $source === 'bulk' || $source === 'bulk-regenerate' ? 'bulk' : 'generate' );
		$tokens_used = isset( $usage_summary['total'] ) ? intval( $usage_summary['total'] ) : ( isset( $usage_summary['total_tokens'] ) ? intval( $usage_summary['total_tokens'] ) : 1 );
		$context     = array(
			'image_id' => $attachment_id,
			'post_id'  => null,
		);
		// Get post ID if attachment has a parent
		$attachment = get_post( $attachment_id );
		if ( $attachment && $attachment->post_parent > 0 ) {
			$context['post_id'] = $attachment->post_parent;
		}
		\BeepBeepAI\AltTextGenerator\Usage\record_usage_event( $user_id, $tokens_used, $action_type, $context );

		$this->record_usage( $usage_summary );

		// Refresh quota cache after successful generation
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
		\BeepBeepAI\AltTextGenerator\Token_Quota_Service::record_local_usage( $tokens_used );

		if ( $has_license ) {
			$this->refresh_license_usage_snapshot();
		}

		// Note: QA review is disabled for API proxy version (quality handled server-side)
		// Persist the generated alt text
		$this->persist_generation_result( $attachment_id, $alt, $usage_summary, $source, $model, $image_strategy, $review_result );

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
			Debug_Log::log(
				'info',
				'Alt text updated',
				array(
					'attachment_id' => $attachment_id,
					'source'        => $source,
					'regenerate'    => (bool) $regenerate,
				),
				'generation'
			);
		}

		return $alt;
	}

	private function queue_attachment( $attachment_id, $source = 'auto' ) {
		$attachment_id = intval( $attachment_id );
		if ( $attachment_id <= 0 || ! $this->is_image( $attachment_id ) ) {
			return false;
		}

		$opts = get_option( self::OPTION_KEY, array() );

		$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( $existing && empty( $opts['force_overwrite'] ) ) {
			return false;
		}

		return Queue::enqueue( $attachment_id, $source ? sanitize_key( $source ) : 'auto' );
	}

	public function register_bulk_action( $bulk_actions ) {
		$bulk_actions['bbai_generate'] = __( 'Generate Alt Text (AI)', 'beepbeep-ai-alt-text-generator' );
		return $bulk_actions;
	}

	public function handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
		if ( $doaction !== 'bbai_generate' ) {
			return $redirect_to;
		}
		$queued = 0;
		foreach ( $post_ids as $id ) {
			if ( $this->queue_attachment( $id, 'bulk' ) ) {
				++$queued;
			}
		}
		if ( $queued > 0 ) {
			Queue::schedule_processing( 10 );

			// Log bulk operation
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				Debug_Log::log(
					'info',
					'Bulk alt text generation queued',
					array(
						'count'          => $queued,
						'total_selected' => count( $post_ids ),
					),
					'bulk'
				);
			}
		}
		return add_query_arg( array( 'bbai_queued' => $queued ), $redirect_to );
	}

	public function row_action_link( $actions, $post ) {
		if ( $post->post_type === 'attachment' && $this->is_image( $post->ID ) ) {
			$has_alt                         = (bool) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
			$generate_label                  = __( 'Generate Alt Text (AI)', 'beepbeep-ai-alt-text-generator' );
			$regenerate_label                = __( 'Regenerate Alt Text (AI)', 'beepbeep-ai-alt-text-generator' );
			$text                            = $has_alt ? $regenerate_label : $generate_label;
			$actions['bbai_generate_single'] = '<a href="#" class="bbai-generate" data-id="' . intval( $post->ID ) . '" data-has-alt="' . ( $has_alt ? '1' : '0' ) . '" data-label-generate="' . esc_attr( $generate_label ) . '" data-label-regenerate="' . esc_attr( $regenerate_label ) . '">' . esc_html( $text ) . '</a>';
		}
		return $actions;
	}

	public function attachment_fields_to_edit( $fields, $post ) {
		if ( ! $this->is_image( $post->ID ) ) {
			return $fields;
		}

		$has_alt          = (bool) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
		$label_generate   = __( 'Generate Alt', 'beepbeep-ai-alt-text-generator' );
		$label_regenerate = __( 'Regenerate Alt', 'beepbeep-ai-alt-text-generator' );
		$current_label    = $has_alt ? $label_regenerate : $label_generate;
		$is_authenticated = $this->api_client->is_authenticated();
		$disabled_attr    = ! $is_authenticated ? ' disabled title="' . esc_attr__( 'Please log in to generate alt text', 'beepbeep-ai-alt-text-generator' ) . '"' : '';
		$button           = sprintf(
			'<button type="button" class="button bbai-generate" data-id="%1$d" data-has-alt="%2$d" data-label-generate="%3$s" data-label-regenerate="%4$s"%5$s>%6$s</button>',
			intval( $post->ID ),
			$has_alt ? 1 : 0,
			esc_attr( $label_generate ),
			esc_attr( $label_regenerate ),
			$disabled_attr,
			esc_html( $current_label )
		);

		// Hide attachment screen field by default to avoid confusion; can be re-enabled via filter
		if ( apply_filters( 'bbai_show_attachment_button', false ) ) {
			$fields['bbai_generate'] = array(
				'label' => __( 'AI Alt Text', 'beepbeep-ai-alt-text-generator' ),
				'input' => 'html',
				'html'  => $button . '<p class="description">' . esc_html__( 'Use AI to suggest alternative text for this image.', 'beepbeep-ai-alt-text-generator' ) . '</p>',
			);
		}

		return $fields;
	}

	/**
	 * @deprecated 4.3.0 Use REST_Controller::register_routes().
	 */
	public function register_rest_routes() {
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\REST_Controller' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-rest-controller.php';
		}

		( new REST_Controller( $this ) )->register_routes();
	}

	public function enqueue_admin( $hook ) {
		$base_path = BBAI_PLUGIN_DIR;
		$base_url  = BBAI_PLUGIN_URL;

		$asset_version = static function ( string $relative, string $fallback = '1.0.0' ) use ( $base_path ): string {
			$relative = ltrim( $relative, '/' );
			$path     = $base_path . $relative;
			return file_exists( $path ) ? (string) filemtime( $path ) : $fallback;
		};

		$use_debug_assets = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$js_base          = $use_debug_assets ? 'assets/src/js/' : 'assets/dist/js/';
		$css_base         = $use_debug_assets ? 'assets/src/css/' : 'assets/dist/css/';
		$asset_path       = static function ( string $base, string $name, bool $debug, string $type ) use ( $base_path ): string {
			$extension     = $debug ? ".$type" : ".min.$type";
			$minified_path = $base . $name . $extension;
			// If minified file doesn't exist, fall back to source file
			if ( ! $debug && ! file_exists( $base_path . $minified_path ) ) {
				$source_base = str_replace( 'assets/dist/', 'assets/src/', $base );
				return $source_base . $name . ".$type";
			}
			return $minified_path;
		};

		$admin_file    = $asset_path( $js_base, 'bbai-admin', $use_debug_assets, 'js' );
		$admin_version = $asset_version( $admin_file, '3.0.0' );

		$checkout_prices = $this->get_checkout_price_ids();

		$l10n_common = array(
			'reviewCue'           => __( 'Visit the ALT Library to double-check the wording.', 'beepbeep-ai-alt-text-generator' ),
			'statusReady'         => '',
			'previewAltHeading'   => __( 'Review generated ALT text', 'beepbeep-ai-alt-text-generator' ),
			'previewAltHint'      => __( 'Review the generated description before applying it to your media item.', 'beepbeep-ai-alt-text-generator' ),
			'previewAltApply'     => __( 'Use this ALT', 'beepbeep-ai-alt-text-generator' ),
			'previewAltCancel'    => __( 'Keep current ALT', 'beepbeep-ai-alt-text-generator' ),
			'previewAltDismissed' => __( 'Preview dismissed. Existing ALT kept.', 'beepbeep-ai-alt-text-generator' ),
			'previewAltShortcut'  => __( 'Shift + Enter for newline.', 'beepbeep-ai-alt-text-generator' ),
		);

		$site_hash_for_js = '';
		if ( method_exists( $this->api_client, 'get_site_id' ) ) {
			$site_hash_for_js = sanitize_text_field( $this->api_client->get_site_id() );
		}
		$site_url_for_js = get_site_url();

		// Load on Media Library and attachment edit contexts (modal also)
		if ( in_array( $hook, array( 'upload.php', 'post.php', 'post-new.php', 'media_page_bbai' ), true ) ) {
			wp_enqueue_script( 'bbai-admin', $base_url . $admin_file, array( 'jquery' ), $admin_version, true );
			wp_localize_script(
				'bbai-admin',
				'BBAI',
				array(
					'nonce'              => wp_create_nonce( 'wp_rest' ),
					'rest'               => esc_url_raw( rest_url( 'bbai/v1/' ) ),
					'restAlt'            => esc_url_raw( rest_url( 'bbai/v1/alt/' ) ),
					'restStats'          => esc_url_raw( rest_url( 'bbai/v1/stats' ) ),
					'restUsage'          => esc_url_raw( rest_url( 'bbai/v1/usage' ) ),
					'restMissing'        => esc_url_raw( add_query_arg( array( 'scope' => 'missing' ), rest_url( 'bbai/v1/list' ) ) ),
					'restAll'            => esc_url_raw( add_query_arg( array( 'scope' => 'all' ), rest_url( 'bbai/v1/list' ) ) ),
					'restQueue'          => esc_url_raw( rest_url( 'bbai/v1/queue' ) ),
					'restRoot'           => esc_url_raw( rest_url() ),
					'restPlans'          => esc_url_raw( rest_url( 'bbai/v1/plans' ) ),
					'restLicenseAttach'  => esc_url_raw( rest_url( 'bbai/v1/license/attach' ) ),
					'l10n'               => $l10n_common,
					'upgradeUrl'         => esc_url( Usage_Tracker::get_upgrade_url() ),
					'billingPortalUrl'   => esc_url( Usage_Tracker::get_billing_portal_url() ),
					'checkoutPrices'     => $checkout_prices,
					'canManage'          => $this->user_can_manage(),
					'inlineBatchSize'    => defined( 'BBAI_INLINE_BATCH' ) ? max( 1, intval( BBAI_INLINE_BATCH ) ) : 1,
					'autoAttachDefaults' => array(
						'siteUrl'   => esc_url_raw( $site_url_for_js ),
						'siteHash'  => sanitize_text_field( $site_hash_for_js ),
						'installId' => sanitize_text_field( $site_hash_for_js ),
					),
				)
			);
			// Also add bbai_ajax for regenerate functionality
			$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
			wp_localize_script(
				'bbai-admin',
				'bbai_ajax',
				array(
					'ajaxurl'    => admin_url( 'admin-ajax.php' ),
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'beepbeepai_nonce' ),
					'can_manage' => $this->user_can_manage(),
					'debug'      => $is_debug,
				)
			);

			// Get user email for billing
			$current_user = wp_get_current_user();
			$user_email   = $current_user->exists() ? $current_user->user_email : '';

			// Add Optti API configuration
			wp_localize_script(
				'bbai-admin',
				'opttiApi',
				array(
					'baseUrl'   => 'https://alttext-ai-backend.onrender.com',
					'plugin'    => 'beepbeep-ai',
					'site'      => home_url(),
					'userEmail' => $user_email,
					'token'     => get_option( 'optti_jwt_token' ) ?: '',
				)
			);
		}

		if ( $hook === 'media_page_bbai' ) {
			$css_file     = $asset_path( $css_base, 'bbai-dashboard', $use_debug_assets, 'css' );
			$js_file      = $asset_path( $js_base, 'bbai-dashboard', $use_debug_assets, 'js' );
			$usage_bridge = $asset_path( $js_base, 'usage-components-bridge', $use_debug_assets, 'js' );
			$upgrade_css  = $asset_path( $css_base, 'upgrade-modal', $use_debug_assets, 'css' );
			$upgrade_js   = $asset_path( $js_base, 'upgrade-modal', $use_debug_assets, 'js' );
			$auth_css     = $asset_path( $css_base, 'auth-modal', $use_debug_assets, 'css' );
			$auth_js      = $asset_path( $js_base, 'auth-modal', $use_debug_assets, 'js' );

			// Enqueue design system (FIRST - foundation for all styles)
			wp_enqueue_style(
				'bbai-design-system',
				$base_url . $asset_path( $css_base, 'design-system', $use_debug_assets, 'css' ),
				array(),
				$asset_version( $asset_path( $css_base, 'design-system', $use_debug_assets, 'css' ), '1.0.0' )
			);

			// Enqueue reusable components (SECOND - uses design tokens)
			wp_enqueue_style(
				'bbai-components',
				$base_url . $asset_path( $css_base, 'components', $use_debug_assets, 'css' ),
				array( 'bbai-design-system' ),
				$asset_version( $asset_path( $css_base, 'components', $use_debug_assets, 'css' ), '1.0.0' )
			);

			// Enqueue page-specific styles (use design system + components)
			wp_enqueue_style(
				'bbai-dashboard',
				$base_url . $css_file,
				array( 'bbai-components' ),
				$asset_version( $css_file, '3.0.0' )
			);

			// Add inline styles for HERO section
			$hero_css = '
                .bbai-hero-section {
                    background: linear-gradient(to bottom, #f7fee7 0%, #ffffff 100%);
                    border-radius: 24px;
                    margin-bottom: 32px;
                    padding: 48px 40px;
                    text-align: center;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                }
                .bbai-hero-content {
                    margin-bottom: 32px;
                }
                .bbai-hero-title {
                    margin: 0 0 16px 0;
                    font-size: 2.5rem;
                    font-weight: 700;
                    color: #0f172a;
                    line-height: 1.2;
                }
                .bbai-hero-subtitle {
                    margin: 0;
                    font-size: 1.125rem;
                    color: #475569;
                    line-height: 1.6;
                    max-width: 600px;
                    margin-left: auto;
                    margin-right: auto;
                }
                .bbai-hero-actions {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 16px;
                    margin-bottom: 24px;
                }
                .bbai-hero-btn-primary {
                    background: linear-gradient(135deg, #14b8a6 0%, #84cc16 100%);
                    color: white;
                    border: none;
                    padding: 16px 32px;
                    border-radius: 16px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: opacity 0.2s ease;
                    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
                }
                .bbai-hero-btn-primary:hover {
                    opacity: 0.9;
                }
                .bbai-hero-link-secondary {
                    background: transparent;
                    border: none;
                    color: #6b7280;
                    text-decoration: underline;
                    font-size: 14px;
                    cursor: pointer;
                    transition: color 0.2s ease;
                    padding: 0;
                }
                .bbai-hero-link-secondary:hover {
                    color: #14b8a6;
                }
                .bbai-hero-micro-copy {
                    font-size: 14px;
                    color: #64748b;
                    font-weight: 500;
                }
            ';
			wp_add_inline_style( 'bbai-dashboard', $hero_css );

			wp_enqueue_style(
				'bbai-modern',
				$base_url . $asset_path( $css_base, 'modern-style', $use_debug_assets, 'css' ),
				array( 'bbai-components' ),
				$asset_version( $asset_path( $css_base, 'modern-style', $use_debug_assets, 'css' ), '4.1.0' )
			);
			wp_enqueue_style(
				'bbai-ui',
				$base_url . $asset_path( $css_base, 'ui', $use_debug_assets, 'css' ),
				array( 'bbai-modern' ),
				$asset_version( $asset_path( $css_base, 'ui', $use_debug_assets, 'css' ), '1.0.0' )
			);
			wp_enqueue_style(
				'bbai-upgrade',
				$base_url . $upgrade_css,
				array( 'bbai-components' ),
				$asset_version( $upgrade_css, '3.2.0' )
			);
			wp_enqueue_style(
				'bbai-auth',
				$base_url . $auth_css,
				array( 'bbai-components' ),
				$asset_version( $auth_css, '4.0.0' )
			);
			wp_enqueue_style(
				'bbai-button-enhancements',
				$base_url . $asset_path( $css_base, 'button-enhancements', $use_debug_assets, 'css' ),
				array( 'bbai-components' ),
				$asset_version( $asset_path( $css_base, 'button-enhancements', $use_debug_assets, 'css' ), '1.0.0' )
			);
			wp_enqueue_style(
				'bbai-guide-settings',
				$base_url . $asset_path( $css_base, 'guide-settings-pages', $use_debug_assets, 'css' ),
				array( 'bbai-components' ),
				$asset_version( $asset_path( $css_base, 'guide-settings-pages', $use_debug_assets, 'css' ), '1.0.0' )
			);
			wp_enqueue_style(
				'bbai-debug-styles',
				$base_url . $asset_path( $css_base, 'bbai-debug', $use_debug_assets, 'css' ),
				array( 'bbai-components' ),
				$asset_version( $asset_path( $css_base, 'bbai-debug', $use_debug_assets, 'css' ), '1.0.0' )
			);
			wp_enqueue_style(
				'bbai-bulk-progress',
				$base_url . $asset_path( $css_base, 'bulk-progress-modal', $use_debug_assets, 'css' ),
				array( 'bbai-components' ),
				$asset_version( $asset_path( $css_base, 'bulk-progress-modal', $use_debug_assets, 'css' ), '1.0.0' )
			);
			wp_enqueue_style(
				'bbai-success-modal',
				$base_url . $asset_path( $css_base, 'success-modal', $use_debug_assets, 'css' ),
				array( 'bbai-components' ),
				$asset_version( $asset_path( $css_base, 'success-modal', $use_debug_assets, 'css' ), '1.0.0' )
			);

			$stats_data = $this->get_media_stats();
			$usage_data = Usage_Tracker::get_stats_display();

			wp_enqueue_script(
				'bbai-dashboard',
				$base_url . $js_file,
				array( 'jquery', 'wp-api-fetch' ),
				$asset_version( $js_file, '3.0.0' ),
				true
			);
			wp_enqueue_script(
				'bbai-upgrade',
				$base_url . $upgrade_js,
				array( 'jquery' ),
				$asset_version( $upgrade_js, '3.1.0' ),
				true
			);
			wp_enqueue_script(
				'bbai-auth',
				$base_url . $auth_js,
				array( 'jquery' ),
				$asset_version( $auth_js, '4.0.0' ),
				true
			);

			// Enqueue usage components bridge (requires React/ReactDOM to be loaded separately)
			if ( file_exists( $base_path . $usage_bridge ) ) {
				wp_enqueue_script(
					'bbai-usage-bridge',
					$base_url . $usage_bridge,
					array( 'bbai-dashboard' ),
					$asset_version( $usage_bridge, '1.0.0' ),
					true
				);
			}
			wp_enqueue_script(
				'bbai-debug',
				$base_url . $asset_path( $js_base, 'bbai-debug', $use_debug_assets, 'js' ),
				array( 'jquery' ),
				$asset_version( $asset_path( $js_base, 'bbai-debug', $use_debug_assets, 'js' ), '1.0.0' ),
				true
			);

			// Localize debug script configuration
			wp_localize_script(
				'bbai-debug',
				'BBAI_DEBUG',
				array(
					'restLogs'  => esc_url_raw( rest_url( 'bbai/v1/logs' ) ),
					'restClear' => esc_url_raw( rest_url( 'bbai/v1/logs/clear' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'initial'   => class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ? Debug_Log::get_logs(
						array(
							'per_page' => 10,
							'page'     => 1,
						)
					) : array(
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
					),
					'strings'   => array(
						'noLogs'       => __( 'No logs recorded yet.', 'beepbeep-ai-alt-text-generator' ),
						'contextTitle' => __( 'Log Context', 'beepbeep-ai-alt-text-generator' ),
						'clearConfirm' => __( 'This will permanently delete all debug logs. Continue?', 'beepbeep-ai-alt-text-generator' ),
						'errorGeneric' => __( 'Unable to load debug logs. Please try again.', 'beepbeep-ai-alt-text-generator' ),
						'emptyContext' => __( 'No additional context was provided for this entry.', 'beepbeep-ai-alt-text-generator' ),
						'cleared'      => __( 'Logs cleared successfully.', 'beepbeep-ai-alt-text-generator' ),
					),
				)
			);

			wp_localize_script(
				'bbai-dashboard',
				'BBAI_DASH',
				array(
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'rest'             => esc_url_raw( rest_url( 'bbai/v1/generate/' ) ),
					'restStats'        => esc_url_raw( rest_url( 'bbai/v1/stats' ) ),
					'restUsage'        => esc_url_raw( rest_url( 'bbai/v1/usage' ) ),
					'restMissing'      => esc_url_raw( add_query_arg( array( 'scope' => 'missing' ), rest_url( 'bbai/v1/list' ) ) ),
					'restAll'          => esc_url_raw( add_query_arg( array( 'scope' => 'all' ), rest_url( 'bbai/v1/list' ) ) ),
					'restQueue'        => esc_url_raw( rest_url( 'bbai/v1/queue' ) ),
					'restRoot'         => esc_url_raw( rest_url() ),
					'restPlans'        => esc_url_raw( rest_url( 'bbai/v1/plans' ) ),
					'upgradeUrl'       => esc_url( Usage_Tracker::get_upgrade_url() ),
					'billingPortalUrl' => esc_url( Usage_Tracker::get_billing_portal_url() ),
					'checkoutPrices'   => $checkout_prices,
					'stats'            => $stats_data,
					'initialUsage'     => $usage_data,
				)
			);

			// Add AJAX variables for regenerate functionality and auth
			$options = get_option( self::OPTION_KEY, array() );
			// Production API URL - always use production
			$production_url = 'https://alttext-ai-backend.onrender.com';
			$api_url        = $production_url;

			wp_localize_script(
				'bbai-dashboard',
				'bbai_ajax',
				array(
					'ajaxurl'          => admin_url( 'admin-ajax.php' ),
					'ajax_url'         => admin_url( 'admin-ajax.php' ),
					'nonce'            => wp_create_nonce( 'beepbeepai_nonce' ),
					'api_url'          => $api_url,
					'is_authenticated' => $this->api_client->is_authenticated(),
					'user_data'        => $this->api_client->get_user_data(),
					'can_manage'       => $this->user_can_manage(),
				)
			);

			wp_localize_script(
				'bbai-dashboard',
				'BBAI_DASH_L10N',
				array(
					'l10n' => array_merge(
						array(
							'processing'        => __( 'Generating ALT text…', 'beepbeep-ai-alt-text-generator' ),
							'processingMissing' => __( 'Generating ALT for #%d…', 'beepbeep-ai-alt-text-generator' ),
							'error'             => __( 'Something went wrong. Check console for details.', 'beepbeep-ai-alt-text-generator' ),
							'summary'           => __( 'Generated %1$d images (%2$d errors).', 'beepbeep-ai-alt-text-generator' ),
							'restUnavailable'   => __( 'REST endpoint unavailable', 'beepbeep-ai-alt-text-generator' ),
							'prepareBatch'      => __( 'Preparing image list…', 'beepbeep-ai-alt-text-generator' ),
							'coverageCopy'      => __( 'of images currently include ALT text.', 'beepbeep-ai-alt-text-generator' ),
							'noRequests'        => __( 'None yet', 'beepbeep-ai-alt-text-generator' ),
							'noAudit'           => __( 'No usage data recorded yet.', 'beepbeep-ai-alt-text-generator' ),
							'nothingToProcess'  => __( 'No images to process.', 'beepbeep-ai-alt-text-generator' ),
							'batchStart'        => __( 'Starting batch…', 'beepbeep-ai-alt-text-generator' ),
							'batchComplete'     => __( 'Batch complete.', 'beepbeep-ai-alt-text-generator' ),
							'batchCompleteAt'   => __( 'Batch complete at %s', 'beepbeep-ai-alt-text-generator' ),
							'completedItem'     => __( 'Finished #%d', 'beepbeep-ai-alt-text-generator' ),
							'failedItem'        => __( 'Failed #%d', 'beepbeep-ai-alt-text-generator' ),
							'loadingButton'     => __( 'Processing…', 'beepbeep-ai-alt-text-generator' ),
						),
						$l10n_common
					),
				)
			);

			// Localize upgrade modal script
			wp_localize_script(
				'bbai-upgrade',
				'BBAI_UPGRADE',
				array(
					'nonce'            => wp_create_nonce( 'beepbeepai_nonce' ),
					'ajaxurl'          => admin_url( 'admin-ajax.php' ),
					'usage'            => $usage_data,
					'upgradeUrl'       => esc_url( Usage_Tracker::get_upgrade_url() ),
					'billingPortalUrl' => esc_url( Usage_Tracker::get_billing_portal_url() ),
					'priceIds'         => $checkout_prices,
					'restPlans'        => esc_url_raw( rest_url( 'bbai/v1/plans' ) ),
					'canManage'        => $this->user_can_manage(),
				)
			);

		}
	}

	public function wpcli_command( $args, $assoc ) {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		$id = isset( $assoc['post_id'] ) ? intval( $assoc['post_id'] ) : 0;
		if ( ! $id ) {
			\WP_CLI::error( 'Provide --post_id=<attachment_id>' );
		}

		$res = $this->generate_and_save( $id, 'wpcli' );
		if ( is_wp_error( $res ) ) {
			if ( $res->get_error_code() === 'bbai_dry_run' ) {
				\WP_CLI::success( "ID $id dry-run: " . $res->get_error_message() );
			} else {
				\WP_CLI::error( $res->get_error_message() );
			}
		} else {
			\WP_CLI::success( "Generated ALT for $id: $res" );
		}
	}

	/**
	 * AJAX handler: Dismiss upgrade notice
	 */
	/**
	 * Handle AJAX request to dismiss external API notice.
	 * Uses site option so it shows only once for all users.
	 */
	public function ajax_dismiss_api_notice() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Store as site option so it shows only once globally, not per user
		update_option( 'wp_alt_text_api_notice_dismissed', true, false );
		wp_send_json_success( array( 'message' => __( 'Notice dismissed', 'beepbeep-ai-alt-text-generator' ) ) );
	}

	public function ajax_dismiss_upgrade() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );

		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		Usage_Tracker::dismiss_upgrade_notice();
		setcookie( 'bbai_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

		wp_send_json_success( array( 'message' => 'Notice dismissed' ) );
	}

	/**
	 * AJAX handler: Refresh usage data
	 */
	public function ajax_queue_retry_job() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}
		$job_id_raw = isset( $_POST['job_id'] ) ? wp_unslash( $_POST['job_id'] ) : '';
		$job_id     = absint( $job_id_raw );
		if ( $job_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'beepbeep-ai-alt-text-generator' ) ) );
		}
		Queue::retry_job( $job_id );
		Queue::schedule_processing( 10 );
		wp_send_json_success( array( 'message' => __( 'Job re-queued.', 'beepbeep-ai-alt-text-generator' ) ) );
	}

	public function ajax_queue_retry_failed() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}
		Queue::retry_failed();
		Queue::schedule_processing( 10 );
		wp_send_json_success( array( 'message' => __( 'Retry scheduled for failed jobs.', 'beepbeep-ai-alt-text-generator' ) ) );
	}

	public function ajax_queue_clear_completed() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}
		Queue::clear_completed();
		wp_send_json_success( array( 'message' => __( 'Cleared completed jobs.', 'beepbeep-ai-alt-text-generator' ) ) );
	}

	public function ajax_queue_stats() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$stats    = Queue::get_stats();
		$failures = Queue::get_failures();

		wp_send_json_success(
			array(
				'stats'    => $stats,
				'failures' => $failures,
			)
		);
	}

	public function ajax_track_upgrade() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$source_raw = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : 'dashboard';
		$source     = sanitize_key( $source_raw );
		$event      = array(
			'source'  => $source,
			'user_id' => get_current_user_id(),
			'time'    => current_time( 'mysql' ),
		);

		update_option( 'bbai_last_upgrade_click', $event, false );
		do_action( 'bbai_upgrade_clicked', $event );

		wp_send_json_success( array( 'recorded' => true ) );
	}

	public function ajax_refresh_usage() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );

		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		// Clear cache and fetch fresh data
		Usage_Tracker::clear_cache();
		$usage = $this->api_client->get_usage();

		if ( $usage ) {
			$stats = Usage_Tracker::get_stats_display();
			wp_send_json_success( $stats );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to fetch usage data' ) );
		}
	}

	/**
	 * AJAX handler: Regenerate single image alt text
	 */
	public function ajax_regenerate_single() {
		// Wrap in try-catch to prevent fatal errors from causing 500 responses
		try {
			// Check nonce - use wp_verify_nonce if check_ajax_referer fails
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'beepbeepai_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ) );
				return;
			}

			if ( ! $this->user_can_manage() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ) );
				return;
			}

			$attachment_id_raw = isset( $_POST['attachment_id'] ) ? wp_unslash( $_POST['attachment_id'] ) : '';
			$attachment_id     = absint( $attachment_id_raw );
			if ( ! $attachment_id ) {
				wp_send_json_error( array( 'message' => 'Invalid attachment ID' ) );
				return;
			}

			// CRITICAL: Log the attachment_id being received to debug why backend sees wrong ID
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'warning',
					'Regenerate request received',
					array(
						'attachment_id_raw' => $attachment_id_raw,
						'attachment_id'     => $attachment_id,
						'post_data_keys'    => array_keys( $_POST ),
					),
					'generation'
				);
			}

			// Ensure API client is initialized
			if ( ! $this->api_client ) {
				// Check if class exists and is properly namespaced
				if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\API_Client_V2' ) ) {
					wp_send_json_error(
						array(
							'message' => __( 'API client class not found. Please deactivate and reactivate the plugin.', 'beepbeep-ai-alt-text-generator' ),
							'code'    => 'class_not_found',
						)
					);
					return;
				}
				$this->api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();
			}

			// Ensure API client methods are available
			if ( ! method_exists( $this->api_client, 'has_active_license' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'API client not properly initialized. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
						'code'    => 'api_client_error',
					)
				);
				return;
			}

			$has_license = $this->api_client->has_active_license();

			// Check if user has reached their limit (skip in local dev mode and for license accounts)
			// Use has_reached_limit() which includes cached usage fallback for better reliability
			if ( ! $has_license && ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV ) ) {
				if ( method_exists( $this->api_client, 'has_reached_limit' ) && $this->api_client->has_reached_limit() ) {
					// Get usage data for the error response (prefer cached if API failed)
					$usage = null;
					if ( method_exists( $this->api_client, 'get_usage' ) ) {
						$usage = $this->api_client->get_usage();
						if ( is_wp_error( $usage ) ) {
							// Fall back to cached usage for display
							require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
							$usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage( false );
						}
					}

					wp_send_json_error(
						array(
							'message' => 'Monthly limit reached',
							'code'    => 'limit_reached',
							'usage'   => is_array( $usage ) ? $usage : null,
						)
					);
					return;
				}
			}

			// Ensure generate_and_save method exists
			if ( ! method_exists( $this, 'generate_and_save' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Generation method not available. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
						'code'    => 'method_not_found',
					)
				);
				return;
			}

			$result = $this->generate_and_save( $attachment_id, 'ajax', 1, array(), true );

			if ( is_wp_error( $result ) ) {
				$error_code    = $result->get_error_code();
				$error_message = $result->get_error_message();
				$error_data    = $result->get_error_data();

				// Provide more user-friendly error messages
				// Use the error message from backend if available (it's already processed by error handler)
				$user_message = $error_message;

				// Handle specific error codes with better messages
				if ( $error_code === 'license_error' ) {
					// License error messages come from backend and are already user-friendly
					// Just use the backend message directly (it's already in $error_message)
					$user_message = $error_message;
				} elseif ( $error_code === 'missing_alt_text' ) {
					$user_message = __( 'The API returned a response but no alt text was generated. This may be a temporary issue. Please try again.', 'beepbeep-ai-alt-text-generator' );
				} elseif ( $error_code === 'api_response_invalid' ) {
					$user_message = __( 'The API response was invalid. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' );
				} elseif ( $error_code === 'quota_check_mismatch' ) {
					$user_message = __( 'Credits appear available but the backend reported a limit. Please try again in a moment.', 'beepbeep-ai-alt-text-generator' );
				} elseif ( $error_code === 'limit_reached' || $error_code === 'quota_exhausted' ) {
					$user_message = __( 'Monthly quota exhausted. Please upgrade to continue generating alt text.', 'beepbeep-ai-alt-text-generator' );
				} elseif ( $error_code === 'api_timeout' ) {
					$user_message = __( 'The request timed out. Please try again.', 'beepbeep-ai-alt-text-generator' );
				} elseif ( $error_code === 'api_unreachable' ) {
					$user_message = __( 'Unable to reach the server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator' );
				}

				wp_send_json_error(
					array(
						'message' => $user_message,
						'code'    => $error_code,
						'data'    => $error_data,
					)
				);
				return;
			}

			\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
			$this->invalidate_stats_cache();

			wp_send_json_success(
				array(
					'message'       => __( 'Alt text generated successfully.', 'beepbeep-ai-alt-text-generator' ),
					'alt_text'      => $result,
					'attachment_id' => $attachment_id,
					'data'          => array(
						'alt_text' => $result,
					),
				)
			);
		} catch ( \Exception $e ) {
			// Log to PHP error log first (more reliable)
			error_log( 'BBAI AJAX Regenerate Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );

			// Try to log to debug log with minimal context to avoid encoding issues
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				try {
					// Use minimal, safe context that won't cause encoding issues
					$error_context = array(
						'error' => substr( $e->getMessage(), 0, 200 ), // Limit message length
						'file'  => basename( $e->getFile() ), // Just filename, not full path
						'line'  => $e->getLine(),
					);
					\BeepBeepAI\AltTextGenerator\Debug_Log::log( 'error', 'AJAX regenerate failed', $error_context, 'generation' );
				} catch ( \Exception $log_error ) {
					// If logging fails, just continue - don't break the error response
					error_log( 'BBAI: Failed to log error: ' . $log_error->getMessage() );
				}
			}

			// Send simple error response
			$error_msg = $e->getMessage();
			if ( empty( $error_msg ) ) {
				$error_msg = 'Unknown error occurred';
			}

			wp_send_json_error(
				array(
					'message' => 'Error: ' . esc_html( substr( $error_msg, 0, 200 ) ),
					'code'    => 'internal_error',
				)
			);
			exit; // Ensure we stop execution
		} catch ( \Error $e ) {
			// Also catch PHP 7+ Error objects (non-Exception errors)
			// Log to PHP error log first (more reliable)
			$error_msg  = $e->getMessage();
			$error_file = $e->getFile();
			$error_line = $e->getLine();

			error_log( 'BBAI AJAX Regenerate Fatal Error: ' . $error_msg . ' in ' . $error_file . ' on line ' . $error_line );

			// Try to log to debug log with minimal context to avoid encoding issues
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				try {
					// Use minimal, safe context that won't cause encoding issues
					$error_context = array(
						'error' => substr( $error_msg, 0, 200 ), // Limit message length
						'file'  => basename( $error_file ), // Just filename, not full path
						'line'  => $error_line,
					);
					\BeepBeepAI\AltTextGenerator\Debug_Log::log( 'error', 'AJAX regenerate fatal error', $error_context, 'generation' );
				} catch ( \Exception $log_error ) {
					// If logging fails, just continue - don't break the error response
					error_log( 'BBAI: Failed to log fatal error: ' . $log_error->getMessage() );
				}
			}

			// Send simple error response
			if ( empty( $error_msg ) ) {
				$error_msg = 'Fatal error occurred';
			}

			wp_send_json_error(
				array(
					'message' => 'Fatal Error: ' . esc_html( substr( $error_msg, 0, 200 ) ),
					'code'    => 'fatal_error',
				)
			);
			exit; // Ensure we stop execution
		}
	}

	/**
	 * Reset site credits for testing
	 * Only available to administrators
	 */
	public function reset_credits_for_testing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'bbai_reset_credits', 'bbai_reset_nonce' );

		// Clear usage cache
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
		\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();

		// Clear token quota service cache
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
		\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();

		// Reset usage to 0
		$reset_ts   = strtotime( 'first day of next month' );
		$usage_data = array(
			'used'           => 0,
			'limit'          => 50,
			'remaining'      => 50,
			'plan'           => 'free',
			'resetDate'      => gmdate( 'Y-m-01', $reset_ts ),
			'resetTimestamp' => $reset_ts,
		);
		\BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage( $usage_data );

		// Clear credit usage logs
		global $wpdb;
		$credit_usage_table = $wpdb->prefix . 'bbai_credit_usage';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $credit_usage_table ) ) === $credit_usage_table ) {
			$wpdb->query( "DELETE FROM `{$credit_usage_table}`" );
		}

		// Clear usage event logs
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
		$usage_logs_table = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $usage_logs_table ) ) === $usage_logs_table ) {
			$wpdb->query( "DELETE FROM `{$usage_logs_table}`" );
		}

		// Clear token quota service local usage
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
		$site_id          = \BeepBeepAI\AltTextGenerator\get_site_identifier();
		$quota_option_key = 'bbai_token_quota_' . md5( $site_id );
		delete_option( $quota_option_key );

		// Invalidate stats cache
		$this->invalidate_stats_cache();

		wp_redirect( add_query_arg( 'bbai_credits_reset', '1', admin_url( 'upload.php?page=bbai-credit-usage' ) ) );
		exit;
	}

	/**
	 * AJAX handler: Bulk queue images for processing
	 */
	public function ajax_bulk_queue() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );

		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$attachment_ids_raw = isset( $_POST['attachment_ids'] ) ? wp_unslash( $_POST['attachment_ids'] ) : array();
		$attachment_ids     = is_array( $attachment_ids_raw ) ? array_map( 'absint', $attachment_ids_raw ) : array();
		$source_raw         = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : 'bulk';
		$source             = sanitize_text_field( $source_raw );

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => 'Invalid attachment IDs' ) );
		}

		// Sanitize all IDs
		$ids = array_map( 'intval', $attachment_ids );
		$ids = array_filter(
			$ids,
			function ( $id ) {
				return $id > 0 && $this->is_image( $id );
			}
		);

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'No valid images found' ) );
		}

		$has_license = $this->api_client->has_active_license();

		// Check if user has remaining usage (skip in local dev mode or when license active)
		if ( ! $has_license && ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV ) ) {
			$usage = $this->api_client->get_usage();

			// If usage check fails due to authentication, allow queueing but warn user
			if ( is_wp_error( $usage ) ) {
				$error_code = $usage->get_error_code();
				// If it's an auth error, allow queueing to proceed (backend will handle it)
				// Don't block queueing on temporary auth issues
				if ( $error_code === 'auth_required' || $error_code === 'user_not_found' ) {
					// Allow queueing - authentication can be handled later during processing
				} else {
					// For other errors (server issues, etc.), still allow queueing
					// The backend will handle usage limits during processing
				}
			} elseif ( ! $usage || ( $usage['remaining'] ?? 0 ) <= 0 ) {
				// Only block if we have a valid usage response showing limit reached
				wp_send_json_error(
					array(
						'message' => 'Monthly limit reached',
						'code'    => 'limit_reached',
						'usage'   => $usage,
					)
				);
			} else {
				// Check how many we can queue
				$remaining = $usage['remaining'] ?? 0;
				if ( count( $ids ) > $remaining ) {
					wp_send_json_error(
						array(
							'message'   => sprintf( __( 'You only have %d generations remaining. Please upgrade or select fewer images.', 'beepbeep-ai-alt-text-generator' ), $remaining ),
							'code'      => 'insufficient_credits',
							'remaining' => $remaining,
						)
					);
				}
			}
		}

		try {
			// Queue images (will clear existing entries for bulk-regenerate)
			$queued = Queue::enqueue_many( $ids, $source );

			// Log bulk queue operation
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				Debug_Log::log(
					'info',
					'Bulk queue operation',
					array(
						'queued'    => $queued,
						'requested' => count( $ids ),
						'source'    => $source,
					),
					'bulk'
				);
			}

			if ( $queued > 0 ) {
				// Schedule queue processing
				Queue::schedule_processing();

				wp_send_json_success(
					array(
						'message' => sprintf( __( '%d image(s) queued for processing', 'beepbeep-ai-alt-text-generator' ), $queued ),
						'queued'  => $queued,
						'total'   => count( $ids ),
					)
				);
			} else {
				// For regeneration, if nothing was queued, it might mean they're already completed
				// Check if images already have alt text and suggest direct regeneration instead

				if ( $source === 'bulk-regenerate' ) {
					wp_send_json_error(
						array(
							'message' => __( 'No images queued. Images may already be processing or have alt text. Refresh the page to see current status.', 'beepbeep-ai-alt-text-generator' ),
							'code'    => 'already_queued',
						)
					);
				} else {
					wp_send_json_error(
						array(
							'message' => __( 'Failed to queue images. They may already be queued or processing.', 'beepbeep-ai-alt-text-generator' ),
						)
					);
				}
			}
		} catch ( \Exception $e ) {
			// Return proper JSON error instead of letting WordPress output HTML
			wp_send_json_error(
				array(
					'message' => __( 'Failed to queue images due to a database error. Please try again.', 'beepbeep-ai-alt-text-generator' ),
					'code'    => 'queue_failed',
				)
			);
		}
	}

	public function process_queue() {
		$batch_size   = apply_filters( 'bbai_queue_batch_size', 3 );
		$max_attempts = apply_filters( 'bbai_queue_max_attempts', 3 );

		Queue::reset_stale( apply_filters( 'bbai_queue_stale_timeout', 10 * MINUTE_IN_SECONDS ) );

		$jobs = Queue::claim_batch( $batch_size );
		if ( empty( $jobs ) ) {
			Queue::purge_completed( apply_filters( 'bbai_queue_purge_age', DAY_IN_SECONDS * 2 ) );
			return;
		}

		foreach ( $jobs as $job ) {
			$attachment_id = intval( $job->attachment_id );
			if ( $attachment_id <= 0 || ! $this->is_image( $attachment_id ) ) {
				Queue::mark_complete( $job->id );
				continue;
			}

			$result = $this->generate_and_save( $attachment_id, $job->source ?? 'queue', max( 0, intval( $job->attempts ) - 1 ) );

			if ( is_wp_error( $result ) ) {
				$code        = $result->get_error_code();
					$message = $result->get_error_message();

				if ( $code === 'limit_reached' ) {
					Queue::mark_retry( $job->id, $message );
					Queue::schedule_processing( apply_filters( 'bbai_queue_limit_delay', HOUR_IN_SECONDS ) );
					break;
				}

				if ( intval( $job->attempts ) >= $max_attempts ) {
					Queue::mark_failed( $job->id, $message );
				} else {
					Queue::mark_retry( $job->id, $message );
				}
				continue;
			}

			Queue::mark_complete( $job->id );
		}

		Usage_Tracker::clear_cache();
		$this->invalidate_stats_cache();
		$stats = Queue::get_stats();
		if ( ! empty( $stats['pending'] ) ) {
			Queue::schedule_processing( apply_filters( 'bbai_queue_next_delay', 45 ) );
		}

		Queue::purge_completed( apply_filters( 'bbai_queue_purge_age', DAY_IN_SECONDS * 2 ) );
	}

	public function handle_media_change( $attachment_id = 0 ) {
		$this->invalidate_stats_cache();

		if ( current_filter() === 'delete_attachment' ) {
			Queue::schedule_processing( 30 );
			return;
		}

		$opts = get_option( self::OPTION_KEY, array() );
		if ( empty( $opts['enable_on_upload'] ) ) {
			return;
		}

		$this->queue_attachment( $attachment_id, 'upload' );
		Queue::schedule_processing( 15 );
	}

	public function handle_media_metadata_update( $data, $post_id ) {
		$this->invalidate_stats_cache();
		$this->queue_attachment( $post_id, 'metadata' );
		Queue::schedule_processing( 20 );
		return $data;
	}

	public function handle_attachment_updated( $post_id, $post_after, $post_before ) {
		$this->invalidate_stats_cache();
		$this->queue_attachment( $post_id, 'update' );
		Queue::schedule_processing( 20 );
	}

	public function handle_post_save( $post_ID, $post, $update ) {
		if ( $post instanceof \WP_Post && $post->post_type === 'attachment' ) {
			$this->invalidate_stats_cache();
			if ( $update ) {
				$this->queue_attachment( $post_ID, 'save' );
				Queue::schedule_processing( 20 );
			}
		}
	}

	private function get_account_summary( ?array $usage_stats = null ) {
		if ( $this->account_summary !== null ) {
			return $this->account_summary;
		}

		$summary = array(
			'email'      => '',
			'name'       => '',
			'plan'       => $usage_stats['plan'] ?? '',
			'plan_label' => $usage_stats['plan_label'] ?? '',
		);

		if ( ! $this->api_client->is_authenticated() ) {
			$this->account_summary = $summary;
			return $this->account_summary;
		}

		$user = $this->api_client->get_user_data();
		if ( ( ! is_array( $user ) || empty( $user['email'] ) ) ) {
			$fresh = $this->api_client->get_user_info();
			if ( ! is_wp_error( $fresh ) && is_array( $fresh ) ) {
				$user = $fresh;
				$this->api_client->set_user_data( $fresh );
			}
		}

		if ( is_array( $user ) ) {
			$summary['email'] = $user['email'] ?? '';
			$summary['name']  = trim( ( $user['firstName'] ?? '' ) . ' ' . ( $user['lastName'] ?? '' ) );
		}

		$this->account_summary = $summary;
		return $this->account_summary;
	}

	/**
	 * Phase 2 Authentication AJAX Handlers
	 */

	/**
	 * AJAX handler: User registration
	 * Prevents multiple free accounts per site
	 */
	public function ajax_register() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );

		// Only admins can register/connect accounts
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only administrators can connect accounts.', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$email_raw = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email     = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
		$password  = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

		if ( empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Email and password are required', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Check if site already has an account
		$existing_token = $this->api_client->get_token();
		if ( ! empty( $existing_token ) ) {
			// Check if it's a free plan
			$usage = $this->api_client->get_usage();
			if ( ! is_wp_error( $usage ) && isset( $usage['plan'] ) && $usage['plan'] === 'free' ) {
				wp_send_json_error(
					array(
						'message' => __( 'This site is already linked to a free account. Ask an administrator to upgrade to Pro or Agency for higher limits.', 'beepbeep-ai-alt-text-generator' ),
						'code'    => 'free_plan_exists',
					)
				);
			}
		}

		$result = $this->api_client->register( $email, $password );

		if ( is_wp_error( $result ) ) {
			$error_code    = $result->get_error_code();
			$error_message = $result->get_error_message();

			// Handle free plan already used error
			if ( $error_code === 'free_plan_exists' || strpos( strtolower( $error_message ), 'free plan' ) !== false ) {
				wp_send_json_error(
					array(
						'message' => __( 'A free plan has already been used for this site. Upgrade to Pro or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator' ),
						'code'    => 'free_plan_exists',
					)
				);
			}

			wp_send_json_error( array( 'message' => $error_message ) );
		}

		// Clear quota cache after successful registration
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
		\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();

		wp_send_json_success(
			array(
				'message' => __( 'Account created successfully', 'beepbeep-ai-alt-text-generator' ),
				'user'    => $result['user'] ?? null,
			)
		);
	}

	/**
	 * AJAX handler: User login
	 */
	public function ajax_login() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$email_raw = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email     = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
		$password  = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

		if ( empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Email and password are required', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$result = $this->api_client->login( $email, $password );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Logged in successfully', 'beepbeep-ai-alt-text-generator' ),
				'user'    => $result['user'] ?? null,
			)
		);
	}

	/**
	 * AJAX handler: User logout
	 */
	public function ajax_logout() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$this->api_client->clear_token();

		wp_send_json_success( array( 'message' => __( 'Logged out successfully', 'beepbeep-ai-alt-text-generator' ) ) );
	}

	public function ajax_disconnect_account() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );

		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Clear JWT token (for authenticated users)
		$this->api_client->clear_token();

		// Clear license key (for agency/license-based users)
		// This prevents automatic reconnection when using license keys
		$this->api_client->clear_license_key();

		// Clear user data
		delete_option( 'opptibbai_user_data' );
		delete_option( 'opptibbai_site_id' );

		// Clear usage cache
		Usage_Tracker::clear_cache();
		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'opptibbai_usage_cache' );
		delete_transient( 'opptibbai_token_last_check' );

		wp_send_json_success(
			array(
				'message' => __( 'Account disconnected. Please sign in again to reconnect.', 'beepbeep-ai-alt-text-generator' ),
			)
		);
	}

	/**
	 * AJAX handler: Activate license key
	 */
	public function ajax_activate_license() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );

		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$license_key_raw = isset( $_POST['license_key'] ) ? wp_unslash( $_POST['license_key'] ) : '';
		$license_key     = is_string( $license_key_raw ) ? sanitize_text_field( $license_key_raw ) : '';

		if ( empty( $license_key ) ) {
			wp_send_json_error( array( 'message' => __( 'License key is required', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Validate UUID format
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $license_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid license key format', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$result = $this->api_client->activate_license( $license_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Clear cached usage data
		Usage_Tracker::clear_cache();
		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'opptibbai_usage_cache' );

		wp_send_json_success(
			array(
				'message'      => __( 'License activated successfully', 'beepbeep-ai-alt-text-generator' ),
				'organization' => $result['organization'] ?? null,
				'site'         => $result['site'] ?? null,
			)
		);
	}

	/**
	 * AJAX handler: Deactivate license key
	 */
	public function ajax_deactivate_license() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );

		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$result = $this->api_client->deactivate_license();

		// Clear cached usage data
		Usage_Tracker::clear_cache();
		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'opptibbai_usage_cache' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'License deactivated successfully', 'beepbeep-ai-alt-text-generator' ),
			)
		);
	}

	/**
	 * AJAX handler: Get license site usage
	 */
	public function ajax_get_license_sites() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Must be authenticated to view license site usage
		if ( ! $this->api_client->is_authenticated() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to view license site usage', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		// Fetch license site usage from API
		$result = $this->api_client->get_license_sites();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message() ?: __( 'Failed to fetch license site usage', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Disconnect a site from the license
	 */
	public function ajax_disconnect_license_site() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Must be authenticated to disconnect license sites
		if ( ! $this->api_client->is_authenticated() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to disconnect license sites', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		$site_id_raw = isset( $_POST['site_id'] ) ? wp_unslash( $_POST['site_id'] ) : '';
		$site_id     = is_string( $site_id_raw ) ? sanitize_text_field( $site_id_raw ) : '';
		if ( empty( $site_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Site ID is required', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		// Disconnect the site from the license
		$result = $this->api_client->disconnect_license_site( $site_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message() ?: __( 'Failed to disconnect site', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Site disconnected successfully', 'beepbeep-ai-alt-text-generator' ),
				'data'    => $result,
			)
		);
	}

	/**
	 * Check if admin is authenticated (separate from regular user auth)
	 */
	private function is_admin_authenticated() {
		// Check if we have a valid admin session
		$admin_session = get_transient( 'bbai_admin_session_' . get_current_user_id() );
		if ( $admin_session === false || empty( $admin_session ) ) {
			return false;
		}

		// Verify session hasn't expired (24 hours)
		$session_time = get_transient( 'bbai_admin_session_time_' . get_current_user_id() );
		if ( $session_time === false || ( time() - intval( $session_time ) ) > ( 24 * HOUR_IN_SECONDS ) ) {
			$this->clear_admin_session();
			return false;
		}

		return true;
	}

	/**
	 * Set admin session
	 */
	private function set_admin_session() {
		$user_id = get_current_user_id();
		set_transient( 'bbai_admin_session_' . $user_id, 'authenticated', DAY_IN_SECONDS );
		set_transient( 'bbai_admin_session_time_' . $user_id, time(), DAY_IN_SECONDS );
	}

	/**
	 * Clear admin session
	 */
	private function clear_admin_session() {
		$user_id = get_current_user_id();
		delete_transient( 'bbai_admin_session_' . $user_id );
		delete_transient( 'bbai_admin_session_time_' . $user_id );
	}

	/**
	 * AJAX handler: Admin login for agency users
	 */
	public function ajax_admin_login() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Verify agency license
		$has_license  = $this->api_client->has_active_license();
		$license_data = $this->api_client->get_license_data();
		$is_agency    = false;

		if ( $has_license && $license_data && isset( $license_data['organization'] ) ) {
			$license_plan = strtolower( $license_data['organization']['plan'] ?? 'free' );
			$is_agency    = ( $license_plan === 'agency' );
		}

		if ( ! $is_agency ) {
			wp_send_json_error(
				array(
					'message' => __( 'Admin access is only available for agency licenses', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		$email_raw    = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email        = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
		$password_raw = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
		$password     = is_string( $password_raw ) ? $password_raw : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		if ( empty( $password ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your password', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		// Attempt login
		$result = $this->api_client->login( $email, $password );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message() ?: __( 'Login failed. Please check your credentials.', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		// Set admin session
		$this->set_admin_session();

		wp_send_json_success(
			array(
				'message'  => __( 'Successfully logged in', 'beepbeep-ai-alt-text-generator' ),
				'redirect' => add_query_arg( array( 'tab' => 'admin' ), admin_url( 'upload.php?page=bbai' ) ),
			)
		);
	}

	/**
	 * AJAX handler: Admin logout
	 */
	public function ajax_admin_logout() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$this->clear_admin_session();

		wp_send_json_success(
			array(
				'message'  => __( 'Logged out successfully', 'beepbeep-ai-alt-text-generator' ),
				'redirect' => add_query_arg( array( 'tab' => 'admin' ), admin_url( 'upload.php?page=bbai' ) ),
			)
		);
	}

	/**
	 * AJAX handler: Get user info
	 */
	public function ajax_get_user_info() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		if ( ! $this->api_client->is_authenticated() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Not authenticated', 'beepbeep-ai-alt-text-generator' ),
					'code'    => 'not_authenticated',
				)
			);
		}

		$user_info = $this->api_client->get_user_info();
		$usage     = $this->api_client->get_usage();

		if ( is_wp_error( $user_info ) ) {
			wp_send_json_error( array( 'message' => $user_info->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'user'  => $user_info,
				'usage' => is_wp_error( $usage ) ? null : $usage,
			)
		);
	}

	/**
	 * AJAX handler: Create Stripe checkout session
	 */
	public function ajax_create_checkout() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Allow checkout without authentication - users can create account during checkout
		// Authentication is optional for checkout, backend will handle account creation

		$price_id_raw = isset( $_POST['price_id'] ) ? wp_unslash( $_POST['price_id'] ) : '';
		$price_id     = sanitize_text_field( $price_id_raw );
		if ( empty( $price_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Price ID is required', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$success_url = admin_url( 'upload.php?page=bbai&checkout=success' );
		$cancel_url  = admin_url( 'upload.php?page=bbai&checkout=cancel' );

		// Create checkout session - works for both authenticated and unauthenticated users
		// If token is invalid, it will retry without token for guest checkout
		$result = $this->api_client->create_checkout_session( $price_id, $success_url, $cancel_url );

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			$error_code    = $result->get_error_code();

			// Don't show "session expired" messages for checkout - just show generic error
			if ( $error_code === 'auth_required' ||
				strpos( strtolower( $error_message ), 'session' ) !== false ||
				strpos( strtolower( $error_message ), 'log in' ) !== false ) {
				$error_message = __( 'Unable to create checkout session. Please try again or contact support.', 'beepbeep-ai-alt-text-generator' );
			}

			wp_send_json_error( array( 'message' => $error_message ) );
		}

		wp_send_json_success(
			array(
				'url'        => $result['url'] ?? '',
				'session_id' => $result['sessionId'] ?? '',
			)
		);
	}

	/**
	 * AJAX handler: Create customer portal session
	 */
	public function ajax_create_portal() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		// Check if user is authenticated via JWT token OR admin session with agency license
		$is_authenticated       = $this->api_client->is_authenticated();
		$is_admin_authenticated = $this->is_admin_authenticated();
		$has_agency_license     = false;

		if ( $is_admin_authenticated || ! $is_authenticated ) {
			// Check if there's an agency license active
			$has_license = $this->api_client->has_active_license();
			if ( $has_license ) {
				$license_data = $this->api_client->get_license_data();
				if ( $license_data && isset( $license_data['organization'] ) ) {
					$license_plan       = strtolower( $license_data['organization']['plan'] ?? 'free' );
					$has_agency_license = ( $license_plan === 'agency' || $license_plan === 'pro' );
				}
			}
		}

		// Allow if authenticated via JWT OR admin-authenticated with agency/pro license
		if ( ! $is_authenticated && ! ( $is_admin_authenticated && $has_agency_license ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to manage billing', 'beepbeep-ai-alt-text-generator' ),
					'code'    => 'not_authenticated',
				)
			);
		}

		// For admin-authenticated users with license, try using stored portal URL first
		if ( $is_admin_authenticated && $has_agency_license && ! $is_authenticated ) {
			$stored_portal_url = Usage_Tracker::get_billing_portal_url();
			if ( ! empty( $stored_portal_url ) ) {
				wp_send_json_success(
					array(
						'url' => $stored_portal_url,
					)
				);
				return;
			}
		}

		$return_url = admin_url( 'upload.php?page=bbai' );
		$result     = $this->api_client->create_customer_portal_session( $return_url );

		if ( is_wp_error( $result ) ) {
			// If backend doesn't support license key auth for portal, provide helpful message
			$error_message = $result->get_error_message();
			$error_message = is_string( $error_message ) ? $error_message : '';
			if ( is_string( $error_message ) && $error_message && ( strpos( (string) $error_message, 'Authentication required' ) !== false ||
				strpos( (string) $error_message, 'Unauthorized' ) !== false ||
				strpos( (string) $error_message, 'not_authenticated' ) !== false ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'To manage your subscription, please log in with your account credentials (not just admin access). If you have an agency license, contact support to access billing management.', 'beepbeep-ai-alt-text-generator' ),
						'code'    => 'not_authenticated',
					)
				);
				return;
			}
			wp_send_json_error( array( 'message' => $error_message ) );
			return;
		}

		wp_send_json_success(
			array(
				'url' => $result['url'] ?? '',
			)
		);
	}

	/**
	 * AJAX handler: Forgot password request
	 */
	public function ajax_forgot_password() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$email_raw = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email     = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		$result = $this->api_client->forgot_password( $email );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		// Pass through all data from backend, including reset link if provided
		$response_data = array(
			'message' => __( 'Password reset link has been sent to your email. Please check your inbox and spam folder.', 'beepbeep-ai-alt-text-generator' ),
		);

		// Include reset link if provided (for development/testing when email service isn't configured)
		if ( isset( $result['resetLink'] ) ) {
			$response_data['resetLink'] = $result['resetLink'];
			$response_data['note']      = $result['note'] ?? __( 'Email service is in development mode. Use this link to reset your password.', 'beepbeep-ai-alt-text-generator' );
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * AJAX handler: Reset password with token
	 */
	public function ajax_reset_password() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$email_raw    = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email        = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
		$token_raw    = isset( $_POST['token'] ) ? wp_unslash( $_POST['token'] ) : '';
		$token        = is_string( $token_raw ) ? sanitize_text_field( $token_raw ) : '';
		$password_raw = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
		$password     = is_string( $password_raw ) ? $password_raw : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		if ( empty( $token ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Reset token is required', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		if ( empty( $password ) || strlen( $password ) < 8 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Password must be at least 8 characters long', 'beepbeep-ai-alt-text-generator' ),
				)
			);
		}

		$result = $this->api_client->reset_password( $email, $token, $password );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Password reset successfully. You can now sign in with your new password.', 'beepbeep-ai-alt-text-generator' ),
				'redirect' => admin_url( 'upload.php?page=bbai&password_reset=success' ),
			)
		);
	}

	/**
	 * AJAX handler: Get subscription information
	 */
	public function ajax_get_subscription_info() {
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		if ( ! $this->api_client->is_authenticated() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to view subscription information', 'beepbeep-ai-alt-text-generator' ),
					'code'    => 'not_authenticated',
				)
			);
		}

		$subscription_info = $this->api_client->get_subscription_info();

		if ( is_wp_error( $subscription_info ) ) {
			wp_send_json_error(
				array(
					'message' => $subscription_info->get_error_message(),
				)
			);
		}

		wp_send_json_success( $subscription_info );
	}

	/**
	 * AJAX handler: Inline generation for selected attachment IDs (used by progress modal)
	 */
	public function ajax_inline_generate() {
		// Start output buffering for AJAX to prevent any output from breaking JSON response
		// This is critical - any echo, warning, or error before wp_send_json_success() will break the response
		if ( ob_get_level() === 0 ) {
			ob_start();
		} else {
			ob_clean();
		}

		// Fix nonce check - use beepbeepai_nonce to match JavaScript
		check_ajax_referer( 'beepbeepai_nonce', 'nonce' );
		if ( ! $this->user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$attachment_ids_raw = isset( $_POST['attachment_ids'] ) ? wp_unslash( $_POST['attachment_ids'] ) : array();
		$attachment_ids     = is_array( $attachment_ids_raw ) ? array_map( 'absint', (array) $attachment_ids_raw ) : array();
		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No attachment IDs provided.', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$ids = array_map( 'intval', $attachment_ids );
		$ids = array_filter(
			$ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment IDs.', 'beepbeep-ai-alt-text-generator' ) ) );
		}

		$results = array();
		foreach ( $ids as $id ) {
			if ( ! $this->is_image( $id ) ) {
				$results[] = array(
					'attachment_id' => $id,
					'success'       => false,
					'message'       => __( 'Attachment is not an image.', 'beepbeep-ai-alt-text-generator' ),
				);
				continue;
			}

			try {
				// CRITICAL: generate_and_save() will only log credits if alt_text is successfully generated
				// It validates alt_text exists before updating usage or logging credits
				$generation = $this->generate_and_save( $id, 'inline', 1, array(), true );

				if ( is_wp_error( $generation ) ) {
					// Generation failed - credits should NOT be logged (handled in generate_and_save)
					$results[] = array(
						'attachment_id' => $id,
						'success'       => false,
						'message'       => $generation->get_error_message(),
						'code'          => $generation->get_error_code(),
					);
				} else {
					// Generation succeeded - credits were already logged in generate_and_save()
					$results[] = array(
						'attachment_id' => $id,
						'success'       => true,
						'alt_text'      => $generation,
						'title'         => get_the_title( $id ),
					);
				}
			} catch ( \Exception $e ) {
				// Catch any unexpected errors during generation
				$results[] = array(
					'attachment_id' => $id,
					'success'       => false,
					'message'       => sprintf( __( 'Unexpected error during generation: %s', 'beepbeep-ai-alt-text-generator' ), $e->getMessage() ),
					'code'          => 'generation_exception',
				);
			} catch ( \Error $e ) {
				// Catch PHP 7+ fatal errors
				$results[] = array(
					'attachment_id' => $id,
					'success'       => false,
					'message'       => sprintf( __( 'Fatal error during generation: %s', 'beepbeep-ai-alt-text-generator' ), $e->getMessage() ),
					'code'          => 'generation_fatal',
				);
			}
		}

		// Clean any output that might have been generated during processing
		// This is critical - any output before wp_send_json_success() will break the JSON response
		if ( ob_get_level() > 0 ) {
			$ob_contents = ob_get_contents();
			if ( ! empty( $ob_contents ) ) {
				// Log what was output (for debugging) but don't send it
				if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
					\BeepBeepAI\AltTextGenerator\Debug_Log::log(
						'warning',
						'Output detected before JSON response in ajax_inline_generate',
						array(
							'output_length'  => strlen( $ob_contents ),
							'output_preview' => substr( $ob_contents, 0, 200 ),
						),
						'ajax'
					);
				}
			}
			ob_clean();
		}

		Usage_Tracker::clear_cache();
		$this->invalidate_stats_cache();

		// Ensure headers haven't been sent (which would break JSON response)
		if ( headers_sent( $file, $line ) ) {
			// Headers already sent - this is a critical error
			// Log it and try to send error response anyway
			if ( class_exists( '\BeepBeepAI\AltTextGenerator\Debug_Log' ) ) {
				\BeepBeepAI\AltTextGenerator\Debug_Log::log(
					'error',
					'Headers already sent in ajax_inline_generate',
					array(
						'file' => $file,
						'line' => $line,
					),
					'ajax'
				);
			}
			// Still try to send JSON - wp_send_json_success handles this
		}

		// wp_send_json_success() will send headers and output, then exit
		// This ensures no output interferes with the JSON response
		wp_send_json_success(
			array(
				'results' => $results,
			)
		);

		// This line should never be reached (wp_send_json_success exits)
		// But included for safety
		if ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}
}

// Class instantiation moved to class-bbai-admin.php bootstrap_core()
// to prevent duplicate menu registration

// Inline JS fallback to add row-action behaviour
add_action(
	'admin_footer-upload.php',
	function () {
		?>
	<script>
	(function($){
		function refreshDashboard(){
			if (!window.BBAI || !BBAI.restStats || !window.fetch){
				return;
			}

			var nonce = (BBAI.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''));
			var headers = {
				'X-WP-Nonce': nonce,
					'Accept': 'application/json'
			};
			var statsUrl = BBAI.restStats + (BBAI.restStats.indexOf('?') === -1 ? '?' : '&') + 'fresh=1';
			var usageUrl = BBAI.restUsage || '';

			Promise.all([
				fetch(statsUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }),
				usageUrl ? fetch(usageUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }) : Promise.resolve(null)
			])
			.then(function(results){
				var stats = results[0], usage = results[1];
				if (!stats){ return; }
				if (typeof window.dispatchEvent === 'function'){
					try {
						window.dispatchEvent(new CustomEvent('bbai-stats-update', { detail: { stats: stats, usage: usage } }));
					} catch(e){}
				}
			})
			.catch(function(){});
		}

		function restore(btn){
			var original = btn.data('original-text');
			btn.text(original || 'Generate Alt');
			if (btn.is('button, input')){
				btn.prop('disabled', false);
			}
		}

		function updateAltField(id, value, context){
			var selectors = [
				'#attachment_alt',
				'#attachments-' + id + '-alt',
				'[data-setting="alt"] textarea',
				'[data-setting="alt"] input',
				'[name="attachments[' + id + '][alt]"]',
				'[name="attachments[' + id + '][_wp_attachment_image_alt]"]',
				'[name="attachments[' + id + '][image_alt]"]',
				'textarea[name="_wp_attachment_image_alt"]',
				'input[name="_wp_attachment_image_alt"]',
				'textarea[aria-label="Alternative Text"]',
				'.attachment-details textarea',
				'.attachment-details input[name*="_wp_attachment_image_alt"]'
			];
			var field;
			selectors.some(function(sel){
				var scoped = context && context.length ? context.find(sel) : $(sel);
				if (scoped.length){
					field = scoped.first();
					return true;
				}
				return false;
			});
			// Hard fallback: directly probe common fields on the attachment edit screen
			if ((!field || !field.length)){
				var fallback = $('#attachment_alt');
				if (!fallback.length){ fallback = $('textarea[name="_wp_attachment_image_alt"]'); }
				if (!fallback.length){ fallback = $('textarea[aria-label="Alternative Text"]'); }
				if (fallback.length){ field = fallback.first(); }
			}
			if (field && field.length){
				field.val(value);
				field.text(value);
				field.attr('value', value);
				field.trigger('input').trigger('change');
			} else {
				// Fallback: update via REST media endpoint (alt_text)
				try {
					var mediaUrl = (window.wp && window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : (window.ajaxurl ? window.ajaxurl.replace('admin-ajax.php', 'index.php?rest_route=/') : '/wp-json/');
					var nonce = (BBAI && BBAI.nonce) ? BBAI.nonce : (window.wpApiSettings ? wpApiSettings.nonce : '');
					if (mediaUrl && nonce){
						fetch(mediaUrl + 'wp/v2/media/' + id, {
							method: 'POST',
							headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
							body: JSON.stringify({ alt_text: value })
						}).then(function(){
							var c = context && context.length ? context : $('.attachment-details');
							var tf = c.find('textarea, input').filter('[name*="_wp_attachment_image_alt"], [aria-label="Alternative Text"], #attachment_alt').first();
							if (tf && tf.length){ tf.val(value).text(value).attr('value', value).trigger('input').trigger('change'); }
						}).catch(function(){});
					}
				} catch(e){}
			}

			if (window.wp && wp.media && typeof wp.media.attachment === 'function'){
				var attachment = wp.media.attachment(id);
				if (attachment){
					try { attachment.set('alt', value); } catch (err) {}
				}
			}
		}

		function pushNotice(type, message){
			if (window.wp && wp.data && wp.data.dispatch){
				try {
					wp.data.dispatch('core/notices').createNotice(type, message, { isDismissible: true });
					return;
				} catch(err) {}
			}
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			var $target = $('#wpbody-content').find('.wrap').first();
			if ($target.length){
				$target.prepend($notice);
			} else {
				$('#wpbody-content').prepend($notice);
			}
		}

		function canManageAccount(){
			return !!(window.BBAI && BBAI.canManage);
		}

		function handleLimitReachedNotice(payload){
			var message = (payload && payload.message) ? payload.message : 'Monthly limit reached. Please contact a site administrator.';
			pushNotice('warning', message);

			if (!canManageAccount()){
				return;
			}

			try {
				// Try bbaiApp namespace first
				if (window.bbaiApp && bbaiApp.upgradeUrl) {
					window.open(bbaiApp.upgradeUrl, '_blank');
				} else if (window.BBAI && BBAI.upgradeUrl) {
					window.open(BBAI.upgradeUrl, '_blank');
				} else if (window.bbaiApp && bbaiApp.upgradeUrl) {
					// Legacy fallback
					window.open(bbaiApp.upgradeUrl, '_blank');
				}
			} catch(e){}

			if (jQuery('.bbai-upgrade-banner').length){
				jQuery('.bbai-upgrade-banner .show-upgrade-modal').trigger('click');
			}
		}

		// Handler for ALT Library regenerate button
		$(document).on('click', '[data-action="regenerate-single"]', function(e){
			e.preventDefault();
			
			var btn = $(this);
			var btnElement = btn[0]; // Get native DOM element
			
			// CRITICAL FIX: Read directly from native DOM attribute to avoid any jQuery caching
			// This is the most reliable way to get the data attribute value
			var attachment_id = btnElement ? btnElement.getAttribute('data-attachment-id') : null;
			
			// Fallback: Try parent row if button doesn't have it
			if (!attachment_id) {
				var parentRow = btn.closest('tr[data-attachment-id]');
				if (parentRow.length) {
					attachment_id = parentRow[0].getAttribute('data-attachment-id');
				}
			}
			
			// Final fallback: Use jQuery methods (but log warning)
			if (!attachment_id) {
				console.warn('WARNING: Could not read attachment_id from HTML attribute, using jQuery fallback');
				attachment_id = btn.attr('data-attachment-id') || btn.data('attachment-id');
			}
			
			// Convert to integer to ensure it's a number
			attachment_id = parseInt(attachment_id, 10);
			
			// CRITICAL: Debug - Log attachment ID multiple ways to catch any issues
			// Use alert() as fallback since console.log might not be showing
			var debugMsg = 'Attachment ID Debug:\n';
			debugMsg += 'Native getAttribute: ' + (btnElement ? btnElement.getAttribute('data-attachment-id') : 'null') + '\n';
			debugMsg += 'jQuery .attr(): ' + (btn.attr('data-attachment-id') || 'null') + '\n';
			debugMsg += 'jQuery .data(): ' + (btn.data('attachment-id') || 'null') + '\n';
			debugMsg += 'Final attachment_id: ' + attachment_id + '\n';
			debugMsg += 'Button HTML: ' + (btnElement ? btnElement.outerHTML.substring(0, 200) : 'null');
			
			console.log('=== REGENERATE DEBUG ===');
			console.log(debugMsg);
			console.log('Button element:', btnElement);
			console.log('Button outerHTML (first 300 chars):', btnElement ? btnElement.outerHTML.substring(0, 300) : 'null');
			console.log('Button getAttribute("data-attachment-id"):', btnElement ? btnElement.getAttribute('data-attachment-id') : 'null');
			console.log('Button .attr("data-attachment-id"):', btn.attr('data-attachment-id'));
			console.log('Button .data("attachment-id"):', btn.data('attachment-id'));
			var parentRowEl = btn.closest('tr[data-attachment-id]')[0];
			console.log('Parent row element:', parentRowEl);
			console.log('Parent row getAttribute("data-attachment-id"):', parentRowEl ? parentRowEl.getAttribute('data-attachment-id') : 'null');
			console.log('Parent row .attr("data-attachment-id"):', btn.closest('tr[data-attachment-id]').attr('data-attachment-id'));
			console.log('Extracted attachment_id (final):', attachment_id);
			console.log('Type of attachment_id:', typeof attachment_id);
			console.log('Is NaN?', isNaN(attachment_id));
			console.log('=======================');
			
			// TEMPORARY: Show alert to force visibility (remove after debugging)
			if (typeof window.bbai_debug_alerts !== 'undefined' && window.bbai_debug_alerts) {
				alert(debugMsg);
			}
			
			if (!attachment_id || isNaN(attachment_id) || attachment_id <= 0){
				console.error('ERROR: Invalid attachment ID:', attachment_id);
				return pushNotice('error', 'AI ALT: Invalid attachment ID. Please refresh the page and try again.');
			}

			if (typeof btn.data('original-text') === 'undefined'){
				btn.data('original-text', btn.text());
			}

			var originalText = btn.data('original-text') || 'Regenerate';
			btn.text('Regenerating…').prop('disabled', true);

			// Get nonce - try multiple sources
			var nonce = (BBAI && BBAI.nonce) || 
						(window.wpApiSettings && wpApiSettings.nonce) || 
						(bbai_ajax && bbai_ajax.nonce) ||
						jQuery('#license-nonce').val() || 
						'';

			// CRITICAL: Debug - Log attachment ID multiple ways to catch any issues
			console.log('=== REGENERATE DEBUG ===');
			console.log('Button native element:', btnElement);
			console.log('Button outerHTML:', btnElement ? btnElement.outerHTML : 'null');
			console.log('Button getAttribute("data-attachment-id"):', btnElement ? btnElement.getAttribute('data-attachment-id') : 'null');
			console.log('Button .attr("data-attachment-id"):', btn.attr('data-attachment-id'));
			console.log('Button .data("attachment-id"):', btn.data('attachment-id'));
			console.log('Parent row getAttribute("data-attachment-id"):', btn.closest('tr[data-attachment-id]')[0] ? btn.closest('tr[data-attachment-id]')[0].getAttribute('data-attachment-id') : 'null');
			console.log('Parent row .attr("data-attachment-id"):', btn.closest('tr[data-attachment-id]').attr('data-attachment-id'));
			console.log('Extracted attachment_id (final):', attachment_id);
			console.log('Type of attachment_id:', typeof attachment_id);
			console.log('Is NaN?', isNaN(attachment_id));
			console.log('=======================');
			
			// Call AJAX endpoint
			var ajaxData = {
				action: 'beepbeepai_regenerate_single',
				nonce: nonce,
				attachment_id: attachment_id,
				// Add timestamp to prevent caching
				_timestamp: Date.now()
			};
			
			console.log('📤 Sending AJAX data:', ajaxData);
			console.log('📤 Sending attachment_id:', attachment_id);
			console.log('📤 Button HTML at send time:', btnElement ? btnElement.outerHTML.substring(0, 300) : 'null');
			
			// CRITICAL: Read attachment_id ONE MORE TIME right before sending (fresh from DOM)
			// This ensures we have the absolute latest value from the HTML
			var final_check_id = null;
			if (btnElement) {
				// Try multiple attributes in order of preference
				final_check_id = btnElement.getAttribute('data-attachment-id') || 
								btnElement.getAttribute('data-image-id') || 
								btnElement.getAttribute('data-id');
			}
			
			// If button doesn't have it, check parent row
			if (!final_check_id) {
				var parentRowCheck = btn.closest('tr[data-attachment-id]')[0];
				if (parentRowCheck) {
					final_check_id = parentRowCheck.getAttribute('data-attachment-id');
				}
			}
			
			// Convert to integer
			final_check_id = final_check_id ? parseInt(final_check_id, 10) : null;
			
			// If we got a valid ID from final check, use it (it's fresher)
			if (final_check_id && !isNaN(final_check_id) && final_check_id > 0) {
				if (final_check_id !== attachment_id) {
					console.warn('⚠️ ATTACHMENT ID MISMATCH! Original:', attachment_id, '→ Using fresh:', final_check_id);
				}
				attachment_id = final_check_id;
				ajaxData.attachment_id = attachment_id;
			}
			
			console.log('✅ FINAL attachment_id being sent:', attachment_id);
			
			jQuery.post(ajaxurl, ajaxData, function(response){
				console.log('✅ Regenerate response:', response);
				restore(btn);
				
				if (response.success){
					// Response structure: {success: true, data: {alt_text: "...", attachment_id: 123, data: {alt_text: "..."}}}
					var altText = (response.data && response.data.alt_text) || 
									(response.data && response.data.data && response.data.data.alt_text) ||
									(typeof response.data === 'string' ? response.data : '');
					
					if (altText && typeof altText === 'string' && altText.length > 0){
						// Check if there's an existing modal with id "bbai-regenerate-modal"
						var existingModal = $('#bbai-regenerate-modal');
						if (existingModal.length && existingModal.is(':visible')){
							// Modal already exists and is visible - update it
							existingModal.find('#bbai-regenerate-content').html(
								'<p style="color: #059669; padding: 10px; background: #d1fae5; border-radius: 4px;">' + 
								'Success! New alt text: ' + altText + '</p>'
							);
							
							// Update apply button to save the alt text
							existingModal.find('.bbai-btn-apply, [data-action="accept"]').off('click').on('click', function(){
								// Use REST API to save alt text
								var saveNonce = (BBAI && BBAI.nonce) || (window.wpApiSettings && wpApiSettings.nonce) || '';
								fetch((BBAI && BBAI.restAlt) || '/wp-json/bbai/v1/alt/' + attachment_id, {
									method: 'POST',
									headers: {
										'X-WP-Nonce': saveNonce,
										'Content-Type': 'application/json'
									},
									body: JSON.stringify({alt_text: altText})
								}).then(function(r){ return r.json(); }).then(function(saveData){
									if (saveData && saveData.alt_text){
										pushNotice('success', 'Alt text updated successfully.');
										existingModal.hide();
										location.reload();
									} else {
										pushNotice('error', 'Failed to save alt text. Please try again.');
									}
								}).catch(function(){
									pushNotice('error', 'Failed to save alt text. Please try again.');
								});
							});
						} else {
							// No modal - just save and reload
							pushNotice('success', 'Alt text regenerated successfully.');
							setTimeout(function(){ location.reload(); }, 1000);
						}
					} else {
						pushNotice('error', 'Alt text was generated but the response format was invalid.');
					}
				} else {
					var errorMsg = (response.data && response.data.message) || 'Failed to regenerate alt text';
					pushNotice('error', errorMsg);
					
					// Check if there's a visible modal to update
					var errorModal = $('#bbai-regenerate-modal');
					if (errorModal.length && errorModal.is(':visible')){
						errorModal.find('#bbai-regenerate-content').html(
							'<p style="color: #dc2626; padding: 10px; background: #fef2f2; border-radius: 4px;">' + 
							errorMsg + '</p>'
						);
					}
					
					if (response.data && response.data.code === 'limit_reached'){
						handleLimitReachedNotice(response.data);
					}
				}
			}).fail(function(xhr, status, error){
				restore(btn);
				var errorMsg = 'Request failed. Please try again.';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message){
					errorMsg = xhr.responseJSON.data.message;
				}
				pushNotice('error', errorMsg);
			});
		});

		$(document).on('click', '.bbai-generate', function(e){
			e.preventDefault();
			if (!window.BBAI || !BBAI.rest){
				return pushNotice('error', 'AI ALT: REST URL missing.');
			}

			var btn = $(this);
			var id = btn.data('id');
			if (!id){ return pushNotice('error', 'AI ALT: Attachment ID missing.'); }

			if (typeof btn.data('original-text') === 'undefined'){
				btn.data('original-text', btn.text());
			}

			btn.text('Generating…');
			if (btn.is('button, input')){
				btn.prop('disabled', true);
			}

			var headers = {'X-WP-Nonce': (BBAI.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''))};
			var context = btn.closest('.compat-item, .attachment-details, .media-modal');

			fetch(BBAI.rest + id, { method:'POST', headers: headers })
				.then(function(r){ return r.json(); })
				.then(function(data){
					if (data && data.alt){
						updateAltField(id, data.alt, context);
						pushNotice('success', 'ALT generated: ' + data.alt);
						if (!context.length){
						location.reload();
						}
						refreshDashboard();
					} else if (data && data.code === 'bbai_dry_run'){
						pushNotice('info', data.message || 'Dry run enabled. Prompt stored for review.');
						refreshDashboard();
					} else if (data && data.code === 'limit_reached'){
						handleLimitReachedNotice(data);
					} else {
						var message = (data && (data.message || (data.data && data.data.message))) || 'Failed to generate ALT';
						pushNotice('error', message);
					}
				})
				.catch(function(err){
					var message = (err && err.message) ? err.message : 'Request failed.';
					pushNotice('error', message);
				})
				.then(function(){ restore(btn); });
		});
	})(jQuery);
	</script>
		<?php
	}
);

// Attachment edit screen behaviour handled via enqueued scripts; inline scripts removed for compliance.

// Inline admin CSS removed; if needed, add via wp_add_inline_style on an enqueued handle.
