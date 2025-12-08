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
		// Ensure capability is set up early, before menu registration.
		$this->core->ensure_capability();

		add_action( 'admin_menu', array( $this->core, 'add_settings_page' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_credit_usage_page' ) );
		add_action( 'admin_init', array( $this->core, 'register_settings' ) );
		add_action( 'add_attachment', array( $this->core, 'handle_media_change' ), 5 );
		add_action( 'delete_attachment', array( $this->core, 'handle_media_change' ), 5 );
		add_action( 'attachment_updated', array( $this->core, 'handle_attachment_updated' ), 5, 3 );
		add_action( 'save_post', array( $this->core, 'handle_post_save' ), 5, 3 );

		add_filter( 'wp_update_attachment_metadata', array( $this->core, 'handle_media_metadata_update' ), 5, 2 );
		add_filter( 'bulk_actions-upload', array( $this->core, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this->core, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'media_row_actions', array( $this->core, 'row_action_link' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this->core, 'attachment_fields_to_edit' ), 15, 2 );

		// Register REST routes - use priority 10 to ensure it runs after WordPress core routes
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this->core, 'enqueue_admin' ) );
		add_action( 'admin_init', array( $this->core, 'maybe_display_threshold_notice' ) );
		add_action( 'admin_init', array( $this->core, 'maybe_handle_direct_checkout' ) );
		add_action( 'admin_notices', array( $this->core, 'maybe_render_checkout_notices' ) );
		add_action( 'admin_post_beepbeepai_usage_export', array( $this->core, 'handle_usage_export' ) );
		add_action( 'admin_post_beepbeepai_debug_export', array( $this->core, 'handle_debug_log_export' ) );
		add_action( 'admin_post_bbai_reset_credits', array( $this->core, 'reset_credits_for_testing' ) );
		add_action( 'init', array( $this->core, 'ensure_capability' ) );
		add_action( 'admin_notices', array( $this->core, 'maybe_render_queue_notice' ) );
		add_action( 'admin_footer', array( $this->core, 'maybe_render_external_api_notice' ) );

		$this->register_ajax_hooks();

		// Initialize Queue and register cron hook.
		\Optti\Framework\Queue::init( 'bbai' );
		add_action( \Optti\Framework\Queue::get_cron_hook(), array( $this->core, 'process_queue' ) );
		add_action( 'beepbeepai_run_migration', array( $this->core, 'run_migration' ) );
		add_action( 'bbai_daily_identity_sync', array( $this->core, 'daily_identity_sync' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'beepbeepai', array( $this->core, 'wpcli_command' ) );
		}
	}

	/**
	 * Register all AJAX handlers.
	 */
	private function register_ajax_hooks() {
		$ajax_actions = array(
			'beepbeepai_dismiss_upgrade'         => 'ajax_dismiss_upgrade',
			'beepbeepai_refresh_usage'           => 'ajax_refresh_usage',
			'beepbeepai_regenerate_single'       => 'ajax_regenerate_single',
			'beepbeepai_bulk_queue'              => 'ajax_bulk_queue',
			'beepbeepai_queue_retry_failed'      => 'ajax_queue_retry_failed',
			'beepbeepai_queue_retry_job'         => 'ajax_queue_retry_job',
			'beepbeepai_queue_clear_completed'   => 'ajax_queue_clear_completed',
			'beepbeepai_queue_stats'             => 'ajax_queue_stats',
			'beepbeepai_track_upgrade'           => 'ajax_track_upgrade',
			'beepbeepai_register'                => 'ajax_register',
			'beepbeepai_login'                   => 'ajax_login',
			'beepbeepai_logout'                  => 'ajax_logout',
			'beepbeepai_disconnect_account'      => 'ajax_disconnect_account',
			'beepbeepai_get_user_info'           => 'ajax_get_user_info',
			'beepbeepai_create_checkout'         => 'ajax_create_checkout',
			'beepbeepai_create_portal'           => 'ajax_create_portal',
			'beepbeepai_forgot_password'         => 'ajax_forgot_password',
			'beepbeepai_reset_password'          => 'ajax_reset_password',
			'beepbeepai_get_subscription_info'   => 'ajax_get_subscription_info',
			'beepbeepai_inline_generate'         => 'ajax_inline_generate',
			'beepbeepai_activate_license'        => 'ajax_activate_license',
			'beepbeepai_deactivate_license'      => 'ajax_deactivate_license',
			'beepbeepai_get_license_sites'       => 'ajax_get_license_sites',
			'beepbeepai_disconnect_license_site' => 'ajax_disconnect_license_site',
			'beepbeepai_admin_login'             => 'ajax_admin_login',
			'beepbeepai_admin_logout'            => 'ajax_admin_logout',
			'beepbeepai_dismiss_api_notice'      => 'ajax_dismiss_api_notice',
			'bbai_clear_signup_transients'       => 'ajax_clear_signup_transients',
			'bbai_log_dashboard_signup_error'    => 'ajax_log_dashboard_signup_error',
			'bbai_save_email_preferences'        => 'ajax_save_email_preferences',
		);

		foreach ( $ajax_actions as $action => $callback ) {
			add_action( "wp_ajax_{$action}", array( $this->core, $callback ) );
		}
	}

	/**
	 * Register credit usage admin page.
	 */
	public static function register_credit_usage_page() {
		require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-credit-usage-page.php';
				Credit_Usage_Page::register_admin_page();
	}
}
