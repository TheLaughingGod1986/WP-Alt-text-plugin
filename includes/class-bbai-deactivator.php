<?php
/**
 * Fired during plugin deactivation.
 */

namespace BeepBeepAI\AltTextGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-core.php';

class Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		$core = new \BeepBeepAI\AltTextGenerator\Core();
		$core->deactivate();
	}
}
