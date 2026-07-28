<?php
/**
 * Lets sibling OptiAI plugins detect each other, and — later — a shared
 * OptiAI Hub, the same way the Titles plugin already hand-detects the Alt
 * Text plugin today. Generalises that one-off into a pattern every module
 * can register itself with, without any hard runtime dependency between
 * independently-distributed WordPress.org plugins.
 *
 * @package OptiAI_Core
 */

namespace OptiAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module_Registry {

	/**
	 * Known modules, keyed by slug. Each plugin registers its own entry on
	 * `plugins_loaded`; a plugin that is not installed simply never adds
	 * itself, so `is_installed()` naturally returns false for it.
	 *
	 * @var array<string,array{basename:string,dashboard_url:string,name:string}>
	 */
	private static $modules = array();

	/**
	 * Register this site's install of one OptiAI module.
	 *
	 * @param string $slug          Module slug, e.g. "alt_text", "titles", "internal_linking".
	 * @param string $basename      Plugin basename (folder/file.php) used for is_plugin_active().
	 * @param string $dashboard_url Admin URL to that module's dashboard.
	 * @param string $name          Display name, e.g. "OptiAI Alt Text".
	 * @return void
	 */
	public static function register( $slug, $basename, $dashboard_url, $name ) {
		self::$modules[ $slug ] = array(
			'basename'      => $basename,
			'dashboard_url' => $dashboard_url,
			'name'          => $name,
		);
	}

	/**
	 * Detect a module's install state without requiring it to have called
	 * register() in *this* request (e.g. checking a sibling plugin before it
	 * has bootstrapped). Mirrors Titles' Admin::get_alt_text_companion().
	 *
	 * @param string $basename Plugin basename (folder/file.php).
	 * @param string $dashboard_url Admin URL to the sibling's dashboard once active.
	 * @param string $install_query_arg Search term used on the Add Plugins screen if not installed.
	 * @return array{state:string,label:string,url:string}
	 */
	public static function detect( $basename, $dashboard_url, $install_query_arg ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $basename ) ) {
			return array(
				'state' => 'active',
				'label' => __( 'Open', 'optiai-core' ),
				'icon'  => 'external',
				'url'   => $dashboard_url,
			);
		}

		if ( file_exists( WP_PLUGIN_DIR . '/' . $basename ) ) {
			return array(
				'state' => 'installed',
				'label' => __( 'Activate', 'optiai-core' ),
				'icon'  => 'play',
				'url'   => add_query_arg(
					array(
						'plugin_status' => 'inactive',
						's'             => $install_query_arg,
					),
					admin_url( 'plugins.php' )
				),
			);
		}

		return array(
			'state' => 'missing',
			'label' => __( 'Install', 'optiai-core' ),
			'icon'  => 'upload',
			'url'   => add_query_arg(
				array(
					'tab'    => 'plugin-information',
					'plugin' => explode( '/', $basename )[0],
				),
				admin_url( 'plugin-install.php' )
			),
		);
	}

	/**
	 * Whether a future OptiAI Hub plugin is active on this site. Modules
	 * check this before rendering their own settings/branding shell so they
	 * can defer to the Hub once it exists; today this always returns false.
	 *
	 * @return bool
	 */
	public static function hub_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'optiai-hub/optiai-hub.php' );
	}

	/**
	 * Every module registered so far in this request — the shape a future
	 * OptiAI Hub would aggregate cross-plugin scores from.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		return self::$modules;
	}

	/**
	 * Pulls every registered module's health report via the shared
	 * `optiai_module_report` filter (see Module_Report::expose()). This is
	 * the cross-plugin aggregation a future OptiAI Hub would use — built
	 * here, ahead of the Hub existing, so both Titles and Alt Text can show
	 * a combined score today without depending on each other at runtime.
	 *
	 * Only modules that are both registered (installed + active on this
	 * site) AND have actually run a scan report; a module with no scan
	 * history yet is silently omitted rather than shown as a fake zero.
	 *
	 * @return array<int,array> Reports keyed positionally, each shaped per Module_Report::build().
	 */
	public static function all_reports() {
		$reports = array();
		foreach ( array_keys( self::$modules ) as $slug ) {
			$report = apply_filters( 'optiai_module_report', array(), $slug );
			if ( is_array( $report ) && isset( $report['score'] ) ) {
				$reports[] = $report;
			}
		}
		return $reports;
	}

	/**
	 * Simple average score across every module that has reported — the
	 * number a future "Overall Website Health" tile on an OptiAI Hub would
	 * show. Returns null when fewer than 2 modules have reported (a single
	 * module's own dashboard already shows its own score; this is only
	 * useful once there is something to combine).
	 *
	 * @return int|null
	 */
	public static function aggregate_score() {
		$reports = self::all_reports();
		if ( count( $reports ) < 2 ) {
			return null;
		}
		$scores = array_map( static function ( $r ) {
			return (int) $r['score'];
		}, $reports );
		return (int) round( array_sum( $scores ) / count( $scores ) );
	}
}
