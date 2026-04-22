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
		add_filter( 'admin_body_class', [ $this->core, 'filter_admin_body_class' ] );
		add_action( 'admin_menu', [ $this->core, 'add_settings_page' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_credit_usage_page' ] );
		add_action( 'admin_init', [ $this->core, 'register_settings' ] );
		// Legacy onboarding redirect removed — dashboard is the single entry point.
		add_action( 'admin_init', [ __CLASS__, 'maybe_clear_usage_cache' ] );
		add_action( 'add_attachment', [ $this->core, 'handle_media_change' ], 5 );
		add_action( 'delete_attachment', [ $this->core, 'handle_media_change' ], 5 );
		add_action( 'attachment_updated', [ $this->core, 'handle_attachment_updated' ], 5, 3 );
		add_action( 'save_post', [ $this->core, 'handle_post_save' ], 5, 3 );

		add_filter( 'wp_update_attachment_metadata', [ $this->core, 'handle_media_metadata_update' ], 5, 2 );
		// Keep manual generation flows inside plugin navigation screens (Dashboard/ALT Library),
		// not in WordPress Media Library row actions, bulk dropdowns, or attachment edit fields.

		add_action( 'rest_api_init', [ $this->rest_controller, 'register_routes' ] );
		add_action( 'admin_enqueue_scripts', [ $this->core, 'enqueue_admin' ] );
		add_action( 'admin_init', [ $this->core, 'maybe_display_threshold_notice' ] );
		add_action( 'admin_init', [ $this->core, 'maybe_handle_direct_checkout' ] );
		add_action( 'admin_notices', [ $this->core, 'maybe_render_checkout_notices' ] );
		add_action( 'admin_post_beepbeepai_usage_export', [ $this->core, 'handle_usage_export' ] );
		add_action( 'admin_post_beepbeepai_debug_export', [ $this->core, 'handle_debug_log_export' ] );
		// Backward compatibility for legacy export action slugs.
		add_action( 'admin_post_bbai_usage_export', [ $this->core, 'handle_usage_export' ] );
		add_action( 'admin_post_bbai_debug_export', [ $this->core, 'handle_debug_log_export' ] );
		add_action( 'admin_post_bbai_logout', [ $this->core, 'handle_logout' ] );
		add_action( 'init', [ $this->core, 'ensure_capability' ] );
		add_action( 'admin_notices', [ $this->core, 'maybe_render_queue_notice' ] );
		add_action( 'admin_footer', [ $this->core, 'maybe_render_external_api_notice' ] );

		$bbai_growth_engine_file = BEEPBEEP_AI_PLUGIN_DIR . 'includes/growth/class-bbai-growth-engine.php';
		if ( is_readable( $bbai_growth_engine_file ) ) {
			require_once $bbai_growth_engine_file;
			\BeepBeepAI\AltTextGenerator\Growth_Engine::init();
		}

		$bbai_phase17_file = BEEPBEEP_AI_PLUGIN_DIR . 'includes/automation/class-bbai-phase17-engine.php';
		if ( is_readable( $bbai_phase17_file ) ) {
			require_once $bbai_phase17_file;
			\BeepBeepAI\AltTextGenerator\Phase17_Engine::init();
		}

		$this->register_ajax_hooks();

		add_action( \BeepBeepAI\AltTextGenerator\Queue::CRON_HOOK, [ $this->core, 'process_queue' ] );
		add_action( 'beepbeepai_run_migration', [ $this->core, 'run_migration' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'beepbeepai', [ $this->core, 'wpcli_command' ] );
		}
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
			'beepbeepai_telemetry'              => 'ajax_telemetry',
			'beepbeepai_register'               => 'ajax_register',
			'beepbeepai_login'                  => 'ajax_login',
			'beepbeepai_logout'                 => 'ajax_logout',
			'bbai_logout'                       => 'ajax_logout',
			'beepbeepai_disconnect_account'     => 'ajax_disconnect_account',
			'beepbeepai_get_user_info'          => 'ajax_get_user_info',
			'beepbeepai_create_checkout'        => 'ajax_create_checkout',
			'beepbeepai_create_portal'          => 'ajax_create_portal',
			'beepbeepai_forgot_password'        => 'ajax_forgot_password',
			'beepbeepai_reset_password'         => 'ajax_reset_password',
			'beepbeepai_get_subscription_info'  => 'ajax_get_subscription_info',
			'beepbeepai_inline_generate'        => 'ajax_inline_generate',
			'beepbeepai_bulk_job_start'         => 'ajax_bulk_job_start',
			'beepbeepai_bulk_job_poll'          => 'ajax_bulk_job_poll',
			'beepbeepai_get_attachment_ids'     => 'ajax_get_attachment_ids',
			'beepbeepai_activate_license'       => 'ajax_activate_license',
			'beepbeepai_deactivate_license'     => 'ajax_deactivate_license',
			'beepbeepai_get_license_sites'      => 'ajax_get_license_sites',
			'beepbeepai_disconnect_license_site' => 'ajax_disconnect_license_site',
			'beepbeepai_admin_login'            => 'ajax_admin_login',
			'beepbeepai_admin_logout'           => 'ajax_admin_logout',
			'bbai_admin_logout'                 => 'ajax_admin_logout',
			'beepbeepai_logout'                 => 'ajax_logout',
			'beepbeepai_dismiss_api_notice'   => 'ajax_dismiss_api_notice',
			'bbai_start_scan'                  => 'ajax_start_scan',
			'bbai_check_milestone'             => 'ajax_check_milestone',
			'bbai_track_milestone'             => 'ajax_track_milestone',
			'bbai_export_analytics'            => 'ajax_export_analytics',
			'bbai_get_activity'                => 'ajax_get_activity',
			'bbai_send_contact_form'           => 'ajax_send_contact_form',
			'bbai_get_contact_submissions'     => 'ajax_get_contact_submissions',
			'bbai_update_contact_submission_status' => 'ajax_update_contact_submission_status',
			'bbai_delete_contact_submission'   => 'ajax_delete_contact_submission',
			'bbai_dismiss_reset_modal'        => 'ajax_dismiss_reset_modal',
			'bbai_debug_logs'                 => 'ajax_debug_logs',
			'bbai_debug_logs_clear'           => 'ajax_debug_logs_clear',
			'bbai_trial_get_missing'          => 'ajax_trial_get_missing',
			'bbai_trial_generate_single'      => 'ajax_trial_generate_single',
			'bbai_trial_demo_generate_batch'  => 'ajax_trial_demo_generate_batch',
			'bbai_scan_missing_alt'           => 'ajax_scan_missing_alt',
			'bbai_start_alt_coverage_scan'   => 'ajax_start_alt_coverage_scan',
			'bbai_poll_alt_coverage_scan'    => 'ajax_poll_alt_coverage_scan',
			'bbai_rescan_alt_coverage'       => 'ajax_rescan_alt_coverage',
			'bbai_generate_preview_alt'       => 'ajax_generate_preview_alt',
			'bbai_apply_alt_batch'            => 'ajax_apply_alt_batch',
			'bbai_complete_setup_wizard'      => 'ajax_complete_setup_wizard',
			'bbai_set_woocommerce_context'    => 'ajax_set_woocommerce_context',
			'bbai_review_prompt_action'       => 'ajax_review_prompt_action',
		];

		foreach ( $ajax_actions as $action => $callback ) {
			add_action( "wp_ajax_{$action}", [ $this->core, $callback ] );
		}
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
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$clear_cache_input = isset( $_GET['clear_cache'] ) ? sanitize_key( wp_unslash( $_GET['clear_cache'] ) ) : '';
		if ( ! in_array( $clear_cache_input, [ '1', 'true', 'yes' ], true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
		$nonce  = isset( $_GET['_bbai_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_bbai_nonce'] ) ) : '';
		$action = 'bbai_clear_cache';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return;
		}

		delete_option( 'beepbeepai_free_credits_allocated' );
		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'bbai_quota_cache' );
		delete_transient( 'bbai_token_last_check' );
		delete_option( 'bbai_usage_stats_cache' );
		delete_option( 'beepbeep_ai_usage_cache' );
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Usage_Tracker' ) ) {
			\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
		}
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Token_Quota_Service' ) ) {
			\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
		}
	}
}
