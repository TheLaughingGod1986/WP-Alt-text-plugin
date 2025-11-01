<?php
/**
 * Define the internationalization functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Alt_Gpt_I18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'ai-alt-gpt',
			false,
			dirname( AI_ALT_GPT_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
