<?php
/**
 * Framework Loader
 *
 * Loads the Optti WordPress Plugin Framework.
 *
 * @package Optti\Framework
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optti Framework Bootstrap Class
 *
 * Provides bootstrap functionality for the Optti framework.
 */
class Optti_Framework {
	/**
	 * Framework configuration.
	 *
	 * @var array
	 */
	protected static $config = [];

	/**
	 * Autoloader registration flag.
	 *
	 * @var bool
	 */
	protected static $autoloader_registered = false;

	/**
	 * Bootstrap the framework.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param array  $config Configuration array.
	 * @return void
	 */
	public static function bootstrap( $plugin_file, $config = [] ) {
		self::$config = wp_parse_args( $config, [
			'plugin_slug'  => '',
			'version'      => '1.0.0',
			'api_base_url' => 'https://alttext-ai-backend.onrender.com',
			'capability'   => 'manage_options',
			'asset_url'    => '',
			'asset_dir'    => '',
		] );

		// Register autoloader.
		self::register_autoloader();

		// Initialize framework.
		self::init();
	}

	/**
	 * Register PSR-4 autoloader for Optti\Framework namespace.
	 *
	 * @return void
	 */
	protected static function register_autoloader() {
		if ( self::$autoloader_registered ) {
			return;
		}

		spl_autoload_register( function( $class ) {
			// Only handle Optti\Framework namespace.
			if ( strpos( $class, 'Optti\\Framework\\' ) !== 0 ) {
				return;
			}

			// Remove namespace prefix.
			$relative_class = str_replace( 'Optti\\Framework\\', '', $class );

			// Convert namespace separators to directory separators.
			$file = str_replace( '\\', '/', $relative_class );

			// Build file path.
			$file_path = __DIR__ . '/src/' . $file . '.php';

			// Load file if it exists.
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
				return true;
			}

			return false;
		}, true, true ); // Prepend autoloader and throw exceptions.

		self::$autoloader_registered = true;
	}

	/**
	 * Initialize framework.
	 *
	 * @return void
	 */
	protected static function init() {
		// Framework initialization happens when plugin classes are instantiated.
		// This method can be extended for framework-wide initialization if needed.
	}

	/**
	 * Get framework configuration.
	 *
	 * @return array Configuration.
	 */
	public static function get_config() {
		return self::$config;
	}
}

/**
 * Get the Plugin instance.
 *
 * @return \Optti\Framework\PluginBase|null Plugin instance.
 */
function optti_plugin() {
	// This function is kept for backward compatibility.
	// In the new structure, plugins should maintain their own instance.
	return null;
}

/**
 * Get the API instance.
 *
 * @return \Optti\Framework\ApiClient ApiClient instance.
 */
function optti_api() {
	return \Optti\Framework\ApiClient::instance();
}

/**
 * Get the Logger instance.
 *
 * @return \Optti\Framework\Logger Logger instance.
 */
function optti_logger() {
	return \Optti\Framework\Logger::instance();
}

/**
 * Get the Cache instance.
 *
 * @return \Optti\Framework\Cache Cache instance.
 */
function optti_cache() {
	return \Optti\Framework\Cache::instance();
}

/**
 * Get the DB instance.
 *
 * @return \Optti\Framework\Db Db instance.
 */
function optti_db() {
	return \Optti\Framework\Db::instance();
}

/**
 * Get the License instance.
 *
 * @return \Optti\Framework\LicenseManager LicenseManager instance.
 */
function optti_license() {
	return \Optti\Framework\LicenseManager::instance();
}
