<?php
/**
 * Check credits used for the last image's alt text generation
 * 
 * Usage: php check-last-image-credits.php
 * Or via WP-CLI: wp eval-file check-last-image-credits.php
 */

// Try multiple WordPress paths
$wp_paths = array(
	__DIR__ . '/../../../../wp-load.php',
	__DIR__ . '/../../../wp-load.php',
	dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',
	'/var/www/html/wp-load.php',
	'/Users/benjaminoats/Library/CloudStorage/SynologyDrive-File-sync/Coding/wp-alt-text-ai/wp-load.php',
	getcwd() . '/wp-load.php',
	getcwd() . '/../wp-load.php',
	getcwd() . '/../../wp-load.php',
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
} elseif ( file_exists( __DIR__ . '/beepbeep-ai-alt-text-generator.php' ) ) {
	require_once __DIR__ . '/beepbeep-ai-alt-text-generator.php';
}

// Load the credit usage logger class
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';

use BeepBeepAI\AltTextGenerator\Credit_Usage_Logger;

global $wpdb;

// Get the last generation record
$last_record = Credit_Usage_Logger::get_last_generation();

if ( ! $last_record ) {
	echo "No credit usage records found.\n";
	echo "This means no alt text generations have been logged yet.\n";
	if ( ! Credit_Usage_Logger::table_exists() ) {
		echo "The credit usage table does not exist yet. It will be created automatically on the first alt text generation.\n";
	}
	exit( 0 );
}

echo "=== Last Image Alt Text Generation ===\n\n";
echo "Attachment ID: {$last_record['attachment_id']}\n";

if ( ! empty( $last_record['attachment_filename'] ) ) {
	echo "Filename: {$last_record['attachment_filename']}\n";
}

if ( ! empty( $last_record['attachment_url'] ) ) {
	echo "Image URL: {$last_record['attachment_url']}\n";
}

if ( ! empty( $last_record['alt_text'] ) ) {
	echo "Alt Text: {$last_record['alt_text']}\n";
}

echo "\n=== Credit Usage ===\n";
echo "Credits Used: {$last_record['credits_used']} tokens\n";

if ( ! empty( $last_record['token_cost'] ) ) {
	$cost = floatval( $last_record['token_cost'] );
	echo "Token Cost: $" . number_format( $cost, 6 ) . "\n";
}

if ( ! empty( $last_record['model'] ) ) {
	echo "Model: {$last_record['model']}\n";
}

if ( ! empty( $last_record['source'] ) ) {
	echo "Source: {$last_record['source']}\n";
}

echo "Generated At: {$last_record['generated_at']}\n";

// Get user info if available
if ( ! empty( $last_record['user_display_name'] ) ) {
	echo "User: {$last_record['user_display_name']}";
	if ( ! empty( $last_record['user_email'] ) ) {
		echo " ({$last_record['user_email']})";
	}
	echo "\n";
} else {
	$user_id = intval( $last_record['user_id'] );
	if ( $user_id > 0 ) {
		echo "User: Unknown (ID: {$user_id})\n";
	} else {
		echo "User: System/Anonymous\n";
	}
}

echo "\n";

