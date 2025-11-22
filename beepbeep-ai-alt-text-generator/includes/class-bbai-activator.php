<?php
/**
 * Fired during plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BBAI_PLUGIN_DIR . 'admin/class-bbai-core.php';

class BbAI_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		$core = new BbAI_Core();
		$core->activate();
	}
}
