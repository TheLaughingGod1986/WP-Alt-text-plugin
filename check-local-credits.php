<?php
/**
 * Check local credit usage table stats
 * Usage: Run via Local by Flywheel WP-CLI or include in WordPress context
 */

// Try to find WordPress
$wp_paths = [
    '/Users/benjaminoats/Local Sites/sandbox/app/public/wp-load.php',
    '/app/public/wp-load.php',
    getcwd() . '/wp-load.php',
    dirname(__DIR__) . '/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("Could not find WordPress. Run this script from within WordPress context or via WP-CLI.\n");
}

if (!defined('BEEPBEEP_AI_PLUGIN_DIR')) {
    define('BEEPBEEP_AI_PLUGIN_DIR', __DIR__ . '/');
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';

global $wpdb;
$table = \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::table();

echo "=== Local WordPress Credit Usage Table Stats ===\n\n";

// Check if table exists
if (!\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::table_exists()) {
    echo "❌ Table '$table' does NOT exist.\n";
    exit(1);
}

echo "✅ Table exists: $table\n\n";

// Get counts
$total_records = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
$total_images = $wpdb->get_var("SELECT COUNT(DISTINCT attachment_id) FROM `$table`");
$total_credits_stored = $wpdb->get_var("SELECT SUM(credits_used) FROM `$table`");
$distinct_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM `$table`");

echo "📊 Table Statistics:\n";
echo "   Total records: $total_records\n";
echo "   Unique images (DISTINCT attachment_id): $total_images\n";
echo "   Sum of credits_used column: $total_credits_stored\n";
echo "   Distinct users: $distinct_users\n\n";

// Get site_usage method output
$site_usage = \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::get_site_usage();
echo "📈 get_site_usage() returns:\n";
echo "   total_images: " . ($site_usage['total_images'] ?? 'N/A') . "\n";
echo "   total_credits: " . ($site_usage['total_credits'] ?? 'N/A') . "\n";
echo "   total_cost: " . ($site_usage['total_cost'] ?? 'N/A') . "\n";
echo "   user_count: " . ($site_usage['user_count'] ?? 'N/A') . "\n\n";

// Show last 5 records
echo "📝 Last 5 records:\n";
$records = $wpdb->get_results("SELECT id, user_id, attachment_id, credits_used, source, generated_at FROM `$table` ORDER BY generated_at DESC LIMIT 5", ARRAY_A);
foreach ($records as $record) {
    echo "   ID: {$record['id']}, User: {$record['user_id']}, Image: {$record['attachment_id']}, Credits: {$record['credits_used']}, Source: {$record['source']}, Date: {$record['generated_at']}\n";
}

echo "\n✅ Done!\n";
