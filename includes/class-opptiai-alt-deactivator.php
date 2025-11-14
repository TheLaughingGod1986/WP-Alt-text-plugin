<?php
/**
 * Fired during plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-core.php';

class Opptiai_Alt_Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		$core = new Opptiai_Alt_Core();
		$core->deactivate();
	}
}
