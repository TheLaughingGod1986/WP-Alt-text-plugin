<?php
/**
 * Define the internationalization functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Opptiai_Alt_I18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'opptiai-alt-text-generator',
			false,
			dirname( OPPTIAI_ALT_PLUGIN_BASENAME ) . '/languages/'
		);
		// Load legacy translations for users who still have the old files in place.
		load_plugin_textdomain(
			'ai-alt-gpt',
			false,
			dirname( OPPTIAI_ALT_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
