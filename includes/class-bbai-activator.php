<?php
/**
 * Fired during plugin activation.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';

class Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		$core = new \BeepBeepAI\AltTextGenerator\Core();
		$core->activate();
	}
}
