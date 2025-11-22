<?php
/**
 * Fired during plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BBAI_PLUGIN_DIR . 'admin/class-bbai-core.php';

class BbAI_Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		$core = new BbAI_Core();
		$core->deactivate();
	}
}
