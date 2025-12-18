<?php
/**
 * Fix Credit Counts Script
 * 
 * This script fixes the credit usage table where credits_used was incorrectly
 * set to OpenAI token counts instead of 1 credit per generation.
 * 
 * Usage: Run via WP-CLI or from WordPress root
 */

// Try to load WordPress
$wp_paths = array(
	__DIR__ . '/../../../../wp-load.php',
	__DIR__ . '/../../../wp-load.php',
	dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',
	'/var/www/html/wp-load.php',
	getcwd() . '/wp-load.php',
	getcwd() . '/../wp-load.php',
);

$wp_loaded = false;
foreach ( $wp_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded || ! defined( 'ABSPATH' ) ) {
	die( "Error: Could not find WordPress. Please run this from your WordPress root directory.\n" );
}

// Load plugin
if ( file_exists( ABSPATH . 'wp-content/plugins/beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php' ) ) {
	require_once ABSPATH . 'wp-content/plugins/beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
}

echo "🔧 Fixing Credit Counts...\n";
echo str_repeat("=", 60) . "\n\n";

global $wpdb;

// Check if table exists
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
$table = \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::table();

if ( ! \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::table_exists() ) {
	echo "❌ Credit usage table does not exist.\n";
	exit( 1 );
}

// Get current stats before fix
$before_stats = $wpdb->get_row(
	"SELECT COUNT(*) as total_records, SUM(credits_used) as total_credits FROM `{$table}`",
	ARRAY_A
);

echo "Before fix:\n";
echo "  - Total records: " . number_format( $before_stats['total_records'] ) . "\n";
echo "  - Total credits: " . number_format( $before_stats['total_credits'] ) . "\n\n";

// Fix: Set all credits_used to 1 (1 credit per generation)
$updated = $wpdb->query(
	"UPDATE `{$table}` SET credits_used = 1 WHERE credits_used != 1"
);

echo "Fixed {$updated} records.\n\n";

// Get stats after fix
$after_stats = $wpdb->get_row(
	"SELECT COUNT(*) as total_records, SUM(credits_used) as total_credits FROM `{$table}`",
	ARRAY_A
);

echo "After fix:\n";
echo "  - Total records: " . number_format( $after_stats['total_records'] ) . "\n";
echo "  - Total credits: " . number_format( $after_stats['total_credits'] ) . "\n\n";

// Clear caches
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
\BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();

echo "✅ Credit counts fixed!\n";
echo "   Each generation now correctly counts as 1 credit.\n";
echo "   Total credits used: " . number_format( $after_stats['total_credits'] ) . " (= " . number_format( $after_stats['total_records'] ) . " images)\n";


