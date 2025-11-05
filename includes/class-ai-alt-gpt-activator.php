<?php
/**
 * Fired during plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AI_ALT_GPT_PLUGIN_DIR . 'admin/class-ai-alt-gpt-core.php';

class Ai_Alt_Gpt_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		$core = new AI_Alt_Text_Generator_GPT();
		$core->activate();
	}
}
