<?php
/**
 * Admin Assets Class
 *
 * Handles CSS and JavaScript asset enqueuing for admin pages.
 *
 * @package Optti\Admin
 */

namespace Optti\Admin;

use Optti\Framework\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Assets
 *
 * Manages admin assets.
 */
class Admin_Assets {

	use Singleton;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Initialize admin assets.
	 */
	protected function __construct() {
		$this->version = defined( 'OPTTI_VERSION' ) ? OPTTI_VERSION : '1.0.0';
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load on Optti admin pages.
		if ( strpos( $hook, 'optti' ) === false ) {
			return;
		}

		// Enqueue styles.
		$this->enqueue_styles();

		// Enqueue scripts.
		$this->enqueue_scripts();

		// Localize scripts.
		$this->localize_scripts();
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @return void
	 */
	protected function enqueue_styles() {
		wp_enqueue_style(
			'optti-admin',
			OPTTI_PLUGIN_URL . 'assets/css/admin.css',
			[],
			$this->version
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	protected function enqueue_scripts() {
		wp_enqueue_script(
			'optti-admin',
			OPTTI_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			$this->version,
			true
		);
	}

	/**
	 * Localize scripts with data.
	 *
	 * @return void
	 */
	protected function localize_scripts() {
		$api = \Optti\Framework\ApiClient::instance();
		$license = \Optti\Framework\LicenseManager::instance();

		wp_localize_script(
			'optti-admin',
			'opttiAdmin',
			[
				'apiUrl'          => rest_url( 'optti/v1/' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'isAuthenticated' => $api->is_authenticated(),
				'hasLicense'      => $license->has_active_license(),
				'cacheEnabled'    => true,
				'cacheTimeout'    => 5 * MINUTE_IN_SECONDS, // 5 minutes for usage, 15 for media stats.
				'strings'         => [
					'error'   => __( 'An error occurred. Please try again.', 'beepbeep-ai-alt-text-generator' ),
					'success' => __( 'Operation completed successfully.', 'beepbeep-ai-alt-text-generator' ),
					'loading' => __( 'Loading...', 'beepbeep-ai-alt-text-generator' ),
				],
			]
		);
	}

	/**
	 * Enqueue a specific asset.
	 *
	 * @param string $handle Asset handle.
	 * @param string $src Asset URL.
	 * @param array  $deps Dependencies.
	 * @param string $version Version.
	 * @param bool   $in_footer Load in footer.
	 * @return void
	 */
	public function enqueue_script( $handle, $src, $deps = [], $version = null, $in_footer = true ) {
		wp_enqueue_script(
			$handle,
			$src,
			$deps,
			$version ?: $this->version,
			$in_footer
		);
	}

	/**
	 * Enqueue a specific style.
	 *
	 * @param string $handle Asset handle.
	 * @param string $src Asset URL.
	 * @param array  $deps Dependencies.
	 * @param string $version Version.
	 * @return void
	 */
	public function enqueue_style( $handle, $src, $deps = [], $version = null ) {
		wp_enqueue_style(
			$handle,
			$src,
			$deps,
			$version ?: $this->version
		);
	}
}

