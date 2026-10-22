<?php
/**
 * Minimal host for the Core_Assets trait. Only collaborators that live outside
 * the trait (on BBAI_Core) are provided here; trait methods run unmodified.
 */
declare(strict_types=1);

if ( ! class_exists( 'BBAI_Test_Core_Assets_Host', false ) ) {
	class BBAI_Test_Core_Assets_Host {
		use \BeepBeepAI\AltTextGenerator\Traits\Core_Assets;

		// Mirrors BBAI_Core's private constants read by the trait.
		private const MENU_SLUG_DASHBOARD  = 'bbai';
		private const MENU_SLUG_LIBRARY    = 'bbai-library';
		private const MENU_SLUG_ONBOARDING = 'bbai-onboarding';
		private const DEFAULT_STRIPE_LINKS = array();

		public $api_client = null;

		public function __construct() {
			$this->api_client = new class() {
				public function get_api_url() { return 'https://api.example.com/'; }
				public function get_token() { return 'token'; }
				public function get_license_key() { return ''; }
				public function has_active_license() { return false; }
				public function is_authenticated() { return true; }
				public function get_user_data() { return array(); }
				public function get_license_data() { return array(); }
			};
		}

		private function get_checkout_price_ids(): array { return array(); }
		private function get_trial_status(): array { return array(); }
		private function get_debug_bootstrap( $force_refresh = false ) { return array(); }
		private function can_show_ui_kit_page(): bool { return false; }
		private function consume_media_upload_upgrade_trigger(): array { return array(); }
		private function get_checkout_stripe_links(): array { return array(); }
		private function get_setup_wizard_bootstrap(): array { return array(); }
		private function sanitize_api_user_data_for_localize( $user_data ): array { return array(); }
		public function get_dashboard_stats_payload( bool $force_refresh = false ): array { return array(); }
		public function is_us_checkout_client(): bool { return false; }
		public function should_use_licensed_bulk_jobs_api(): bool { return false; }
		public function user_can_manage() { return true; }
	}
}
