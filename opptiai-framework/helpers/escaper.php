<?php
/**
 * OpptiAI Framework - Escaping Helpers
 *
 * @package OpptiAI\Framework\Helpers
 */

namespace OpptiAI\Framework\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Escaper {

	/**
	 * Escape HTML
	 *
	 * @param string $text Raw text
	 * @return string Escaped HTML
	 */
	public static function html( $text ) {
		return esc_html( $text );
	}

	/**
	 * Escape HTML attribute
	 *
	 * @param string $text Raw text
	 * @return string Escaped attribute
	 */
	public static function attr( $text ) {
		return esc_attr( $text );
	}

	/**
	 * Escape URL
	 *
	 * @param string $url Raw URL
	 * @return string Escaped URL
	 */
	public static function url( $url ) {
		return esc_url( $url );
	}

	/**
	 * Escape JavaScript
	 *
	 * @param string $text Raw text
	 * @return string Escaped JavaScript
	 */
	public static function js( $text ) {
		return esc_js( $text );
	}

	/**
	 * Escape textarea content
	 *
	 * @param string $text Raw text
	 * @return string Escaped textarea
	 */
	public static function textarea( $text ) {
		return esc_textarea( $text );
	}

	/**
	 * Escape and allow safe HTML (posts, comments)
	 *
	 * @param string $content Raw content
	 * @return string Escaped content with allowed tags
	 */
	public static function kses_post( $content ) {
		return wp_kses_post( $content );
	}

	/**
	 * Escape SQL (prefer wpdb->prepare instead)
	 *
	 * @param string $text Raw text
	 * @return string Escaped SQL
	 */
	public static function sql( $text ) {
		global $wpdb;
		return $wpdb->_real_escape( $text );
	}

	/**
	 * Escape XML
	 *
	 * @param string $text Raw text
	 * @return string Escaped XML
	 */
	public static function xml( $text ) {
		return esc_xml( $text );
	}

	/**
	 * Safely output JSON
	 *
	 * @param mixed $data Data to encode
	 * @return string JSON string
	 */
	public static function json( $data ) {
		return wp_json_encode( $data );
	}
}
