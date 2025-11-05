<?php
/**
 * Register WordPress hooks for the Alt Text AI core implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Alt_Gpt_Admin_Hooks {

	/**
	 * @var AI_Alt_Text_Generator_GPT
	 */
	private $core;

	/**
	 * @var Ai_Alt_Gpt_REST_Controller
	 */
	private $rest_controller;

	/**
	 * Constructor.
	 *
	 * @param AI_Alt_Text_Generator_GPT $core Core implementation instance.
	 */
	public function __construct( AI_Alt_Text_Generator_GPT $core ) {
		$this->core            = $core;
		$this->rest_controller = new Ai_Alt_Gpt_REST_Controller( $core );
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function register() {
		add_filter( 'plugins_api', [ $this->core, 'override_plugin_information' ], 10, 3 );
		add_filter( 'plugin_row_meta', [ $this->core, 'plugin_row_meta' ], 10, 4 );

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
		add_action( 'admin_post_ai_alt_usage_export', [ $this->core, 'handle_usage_export' ] );
		add_action( 'init', [ $this->core, 'ensure_capability' ] );
		add_action( 'admin_notices', [ $this->core, 'maybe_render_queue_notice' ] );

		$this->register_ajax_hooks();

		add_action( AltText_AI_Queue::CRON_HOOK, [ $this->core, 'process_queue' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ai-alt', [ $this->core, 'wpcli_command' ] );
		}
	}

	/**
	 * Register all AJAX handlers.
	 */
	private function register_ajax_hooks() {
		$ajax_actions = [
			'alttextai_dismiss_upgrade'        => 'ajax_dismiss_upgrade',
			'alttextai_refresh_usage'          => 'ajax_refresh_usage',
			'alttextai_regenerate_single'      => 'ajax_regenerate_single',
			'alttextai_bulk_queue'             => 'ajax_bulk_queue',
			'alttextai_queue_retry_failed'     => 'ajax_queue_retry_failed',
			'alttextai_queue_retry_job'        => 'ajax_queue_retry_job',
			'alttextai_queue_clear_completed'  => 'ajax_queue_clear_completed',
			'alttextai_queue_stats'            => 'ajax_queue_stats',
			'alttextai_track_upgrade'          => 'ajax_track_upgrade',
			'alttextai_register'               => 'ajax_register',
			'alttextai_login'                  => 'ajax_login',
			'alttextai_logout'                 => 'ajax_logout',
			'alttextai_get_user_info'          => 'ajax_get_user_info',
			'alttextai_create_checkout'        => 'ajax_create_checkout',
			'alttextai_create_portal'          => 'ajax_create_portal',
			'alttextai_forgot_password'        => 'ajax_forgot_password',
			'alttextai_reset_password'         => 'ajax_reset_password',
			'alttextai_get_subscription_info'  => 'ajax_get_subscription_info',
		];

		foreach ( $ajax_actions as $action => $callback ) {
			add_action( "wp_ajax_{$action}", [ $this->core, $callback ] );
		}
	}
}
