<?php
/**
 * Register WordPress hooks for the Alt Text AI core implementation.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Hooks {

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @var REST_Controller
	 */
	private $rest_controller;

	/**
	 * Constructor.
	 *
	 * @param Core $core Core implementation instance.
	 */
	public function __construct( Core $core ) {
		$this->core            = $core;
		$this->rest_controller = new REST_Controller( $core );
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function register() {
		add_action( 'admin_menu', [ $this->core, 'add_settings_page' ] );
		$enable_new_dashboard = apply_filters( 'bbai_enable_new_dashboard', false );
		if ( $enable_new_dashboard ) {
			$this->register_dashboard_page();
		}
		add_action( 'admin_menu', [ __CLASS__, 'register_credit_usage_page' ] );
		add_action( 'admin_init', [ $this->core, 'register_settings' ] );
		add_action( 'admin_init', [ $this->core, 'maybe_redirect_to_onboarding' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_clear_usage_cache' ] );
		add_action( 'add_attachment', [ $this->core, 'handle_media_change' ], 5 );
		add_action( 'delete_attachment', [ $this->core, 'handle_media_change' ], 5 );
		add_action( 'attachment_updated', [ $this->core, 'handle_attachment_updated' ], 5, 3 );
		add_action( 'save_post', [ $this->core, 'handle_post_save' ], 5, 3 );

		add_filter( 'wp_update_attachment_metadata', [ $this->core, 'handle_media_metadata_update' ], 5, 2 );
		add_filter( 'bulk_actions-upload', [ $this->core, 'register_bulk_action' ] );
		add_filter( 'handle_bulk_actions-upload', [ $this->core, 'handle_bulk_action' ], 10, 3 );
		add_filter( 'media_row_actions', [ $this->core, 'row_action_link' ], 10, 2 );
		add_filter( 'attachment_fields_to_edit', [ $this->core, 'attachment_fields_to_edit' ], 15, 2 );

		add_action( 'rest_api_init', [ $this->rest_controller, 'register_routes' ] );
		add_action( 'admin_enqueue_scripts', [ $this->core, 'enqueue_admin' ] );
		add_action( 'admin_init', [ $this->core, 'maybe_display_threshold_notice' ] );
		add_action( 'admin_init', [ $this->core, 'maybe_handle_direct_checkout' ] );
		add_action( 'admin_notices', [ $this->core, 'maybe_render_checkout_notices' ] );
		add_action( 'admin_post_beepbeepai_usage_export', [ $this->core, 'handle_usage_export' ] );
		add_action( 'admin_post_beepbeepai_debug_export', [ $this->core, 'handle_debug_log_export' ] );
		add_action( 'admin_post_bbai_logout', [ $this->core, 'handle_logout' ] );
		add_action( 'init', [ $this->core, 'ensure_capability' ] );
		add_action( 'admin_notices', [ $this->core, 'maybe_render_queue_notice' ] );
		add_action( 'admin_footer', [ $this->core, 'maybe_render_external_api_notice' ] );

		$this->register_ajax_hooks();
		if ( $enable_new_dashboard ) {
			$this->register_dashboard_ajax_hooks();
		}

		add_action( \BeepBeepAI\AltTextGenerator\Queue::CRON_HOOK, [ $this->core, 'process_queue' ] );
		add_action( 'beepbeepai_run_migration', [ $this->core, 'run_migration' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'beepbeepai', [ $this->core, 'wpcli_command' ] );
		}
	}

	/**
	 * Register the standalone dashboard page and assets.
	 */
	private function register_dashboard_page() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-bbai-admin-dashboard.php';

		$dashboard = new Admin_Dashboard();

		add_action( 'admin_menu', [ $dashboard, 'register_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $dashboard, 'enqueue_assets' ] );
	}

	/**
	 * Register all AJAX handlers.
	 */
	private function register_ajax_hooks() {
		$ajax_actions = [
			'beepbeepai_dismiss_upgrade'        => 'ajax_dismiss_upgrade',
			'beepbeepai_refresh_usage'          => 'ajax_refresh_usage',
			'beepbeepai_regenerate_single'      => 'ajax_regenerate_single',
			'beepbeepai_bulk_queue'             => 'ajax_bulk_queue',
			'beepbeepai_queue_retry_failed'     => 'ajax_queue_retry_failed',
			'beepbeepai_queue_retry_job'        => 'ajax_queue_retry_job',
			'beepbeepai_queue_clear_completed'  => 'ajax_queue_clear_completed',
			'beepbeepai_queue_stats'            => 'ajax_queue_stats',
			'beepbeepai_track_upgrade'          => 'ajax_track_upgrade',
			'beepbeepai_register'               => 'ajax_register',
			'beepbeepai_login'                  => 'ajax_login',
			'beepbeepai_logout'                 => 'ajax_logout',
			'beepbeepai_disconnect_account'     => 'ajax_disconnect_account',
			'beepbeepai_get_user_info'          => 'ajax_get_user_info',
			'beepbeepai_create_checkout'        => 'ajax_create_checkout',
			'beepbeepai_create_portal'          => 'ajax_create_portal',
			'beepbeepai_forgot_password'        => 'ajax_forgot_password',
			'beepbeepai_reset_password'         => 'ajax_reset_password',
			'beepbeepai_get_subscription_info'  => 'ajax_get_subscription_info',
			'beepbeepai_inline_generate'        => 'ajax_inline_generate',
			'beepbeepai_activate_license'       => 'ajax_activate_license',
			'beepbeepai_deactivate_license'     => 'ajax_deactivate_license',
			'beepbeepai_get_license_sites'      => 'ajax_get_license_sites',
			'beepbeepai_disconnect_license_site' => 'ajax_disconnect_license_site',
			'beepbeepai_admin_login'            => 'ajax_admin_login',
			'beepbeepai_admin_logout'           => 'ajax_admin_logout',
			'beepbeepai_dismiss_api_notice'   => 'ajax_dismiss_api_notice',
			'bbai_check_onboarding'            => 'ajax_check_onboarding',
			'bbai_complete_onboarding'         => 'ajax_complete_onboarding',
			'bbai_start_scan'                  => 'ajax_start_scan',
			'bbai_onboarding_skip'             => 'ajax_onboarding_skip',
			'bbai_check_milestone'             => 'ajax_check_milestone',
			'bbai_track_milestone'             => 'ajax_track_milestone',
			'bbai_export_analytics'            => 'ajax_export_analytics',
			'bbai_get_activity'                => 'ajax_get_activity',
			'bbai_send_contact_form'           => 'ajax_send_contact_form',
			'bbai_get_contact_submissions'     => 'ajax_get_contact_submissions',
			'bbai_update_contact_submission_status' => 'ajax_update_contact_submission_status',
			'bbai_delete_contact_submission'   => 'ajax_delete_contact_submission',
		];

		foreach ( $ajax_actions as $action => $callback ) {
			add_action( "wp_ajax_{$action}", [ $this->core, $callback ] );
		}
	}

	/**
	 * Register AJAX stubs for the dashboard actions.
	 */
	private function register_dashboard_ajax_hooks() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-bbai-admin-dashboard.php';

		$dashboard = new Admin_Dashboard();

		add_action( 'wp_ajax_bbai_generate_missing', [ $dashboard, 'ajax_generate_missing' ] );
		add_action( 'wp_ajax_bbai_reoptimize_all', [ $dashboard, 'ajax_reoptimize_all' ] );
	}

	/**
	 * Register credit usage admin page.
	 */
	public static function register_credit_usage_page() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-credit-usage-page.php';
		Credit_Usage_Page::register_admin_page();
	}

	/**
	 * Clear usage cache when ?clear_cache=1 is in URL with valid nonce.
	 */
	public static function maybe_clear_usage_cache() {
		$clear_cache_raw = isset( $_GET['clear_cache'] ) ? wp_unslash( $_GET['clear_cache'] ) : '';
		$clear_cache     = is_string( $clear_cache_raw ) ? sanitize_text_field( $clear_cache_raw ) : '';

		if ( $clear_cache !== '1' ) {
			return;
		}

		// Verify nonce for cache clearing action.
		$nonce_raw = isset( $_GET['_bbai_nonce'] ) ? wp_unslash( $_GET['_bbai_nonce'] ) : '';
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bbai_clear_cache' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'bbai_token_last_check' );
		// Also clear any usage tracker cache.
		delete_option( 'bbai_usage_stats_cache' );
		delete_option( 'beepbeep_ai_usage_cache' );
	}
}
