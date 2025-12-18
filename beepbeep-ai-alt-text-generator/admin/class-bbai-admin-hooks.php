<?php
declare(strict_types=1);

/**
 * Register WordPress hooks for the Alt Text AI core implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BbAI_Admin_Hooks {

	/**
	 * @var BbAI_Core
	 */
	private $core;

	/**
	 * @var BbAI_REST_Controller
	 */
	private $rest_controller;

	/**
	 * Constructor.
	 *
	 * @param BbAI_Core $core Core implementation instance.
	 */
	public function __construct( BbAI_Core $core ) {
		$this->core            = $core;
		$this->rest_controller = new BbAI_REST_Controller( $core );
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function register() {
		add_action( 'admin_menu', [ $this->core, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this->core, 'register_settings' ] );
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
		add_action( 'admin_post_bbai_usage_export', [ $this->core, 'handle_usage_export' ] );
		add_action( 'admin_post_bbai_debug_export', [ $this->core, 'handle_debug_log_export' ] );
		add_action( 'init', [ $this->core, 'ensure_capability' ] );
		add_action( 'admin_notices', [ $this->core, 'maybe_render_queue_notice' ] );
		add_action( 'admin_footer', [ $this->core, 'maybe_render_external_api_notice' ] );

		$this->register_ajax_hooks();

		add_action( BbAI_Queue::CRON_HOOK, [ $this->core, 'process_queue' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'beepbeepai', [ $this->core, 'wpcli_command' ] );
		}
	}

	/**
	 * Register all AJAX handlers.
	 */
	private function register_ajax_hooks() {
		$ajax_actions = [
			'bbai_dismiss_upgrade'        => 'ajax_dismiss_upgrade',
			'bbai_refresh_usage'          => 'ajax_refresh_usage',
			'bbai_regenerate_single'      => 'ajax_regenerate_single',
			'bbai_bulk_queue'             => 'ajax_bulk_queue',
			'bbai_queue_retry_failed'     => 'ajax_queue_retry_failed',
			'bbai_queue_retry_job'        => 'ajax_queue_retry_job',
			'bbai_queue_clear_completed'  => 'ajax_queue_clear_completed',
			'bbai_queue_stats'            => 'ajax_queue_stats',
			'bbai_track_upgrade'          => 'ajax_track_upgrade',
			'bbai_register'               => 'ajax_register',
			'bbai_login'                  => 'ajax_login',
			'bbai_logout'                 => 'ajax_logout',
			'bbai_disconnect_account'     => 'ajax_disconnect_account',
			'bbai_get_user_info'          => 'ajax_get_user_info',
			'bbai_create_checkout'        => 'ajax_create_checkout',
			'bbai_create_portal'          => 'ajax_create_portal',
			'bbai_forgot_password'        => 'ajax_forgot_password',
			'bbai_reset_password'         => 'ajax_reset_password',
			'bbai_get_subscription_info'  => 'ajax_get_subscription_info',
			'bbai_inline_generate'        => 'ajax_inline_generate',
			'bbai_activate_license'       => 'ajax_activate_license',
			'bbai_deactivate_license'     => 'ajax_deactivate_license',
			'bbai_get_license_sites'      => 'ajax_get_license_sites',
			'bbai_disconnect_license_site' => 'ajax_disconnect_license_site',
			'bbai_admin_login'            => 'ajax_admin_login',
			'bbai_admin_logout'           => 'ajax_admin_logout',
			'bbai_dismiss_api_notice'   => 'ajax_dismiss_api_notice',
		];

		foreach ( $ajax_actions as $action => $callback ) {
			add_action( "wp_ajax_{$action}", [ $this->core, $callback ] );
		}
	}
}
