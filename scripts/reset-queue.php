<?php
/**
 * Reset stuck queue jobs
 * 
 * This script resets all processing jobs back to pending status
 * and clears all failed jobs.
 * 
 * Usage: wp eval-file scripts/reset-queue.php
 * Or: php scripts/reset-queue.php (requires WordPress bootstrap)
 */

if (php_sapi_name() === 'cli') {
    // Load WordPress if running directly
    // Try multiple possible paths
    $wp_load_paths = [
        dirname(__DIR__) . '/../../../../wp-load.php', // Relative from plugin
        '/var/www/html/wp-load.php', // Docker container path
        dirname(__DIR__) . '/../../../wp-load.php', // Alternative relative path
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("Could not find wp-load.php. Please run this via WP-CLI: wp eval-file scripts/reset-queue.php\n");
    }
}

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

global $wpdb;

// Load the queue class
if (!class_exists('BbAI_Queue')) {
    require_once dirname(__DIR__) . '/includes/class-queue.php';
}

$table = BbAI_Queue::table();

echo "Resetting stuck queue jobs...\n\n";

// Reset all processing jobs to pending
$processing_reset = $wpdb->query(
    $wpdb->prepare(
        "UPDATE {$table} SET status = %s, locked_at = NULL WHERE status = %s",
        'pending',
        'processing'
    )
);

echo "✓ Reset {$processing_reset} processing job(s) back to pending\n";

// Clear all failed jobs
$failed_cleared = $wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$table} WHERE status = %s",
        'failed'
    )
);

echo "✓ Cleared {$failed_cleared} failed job(s)\n\n";

// Show current stats
$stats = BbAI_Queue::get_stats();
echo "Current Queue Status:\n";
echo "  Pending: {$stats['pending']}\n";
echo "  Processing: {$stats['processing']}\n";
echo "  Failed: {$stats['failed']}\n";
echo "  Completed: {$stats['completed']}\n\n";

echo "Done!\n";

