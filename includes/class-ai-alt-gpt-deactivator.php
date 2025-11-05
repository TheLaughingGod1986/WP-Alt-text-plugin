<?php
/**
 * Fired during plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AI_ALT_GPT_PLUGIN_DIR . 'admin/class-ai-alt-gpt-core.php';

class Ai_Alt_Gpt_Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		$core = new AI_Alt_Text_Generator_GPT();
		$core->deactivate();
	}
}
