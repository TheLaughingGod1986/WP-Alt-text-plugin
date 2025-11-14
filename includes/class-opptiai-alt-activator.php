<?php
/**
 * Fired during plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-core.php';

class Opptiai_Alt_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		$core = new Opptiai_Alt_Core();
		$core->activate();
	}
}
