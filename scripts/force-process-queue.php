<?php
/**
 * Force Process Queue - Manually trigger queue processing
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-queue.php';
// Get stats before
$stats_before = AltText_AI_Queue::get_stats();
echo "Before:\n";
echo "  Pending: " . $stats_before['pending'] . "\n";
echo "  Processing: " . $stats_before['processing'] . "\n";
echo "  Failed: " . $stats_before['failed'] . "\n\n";

// Trigger the queue processing via action hook
echo "Processing queue via action hook...\n";
do_action('ai_alt_process_queue');

// Wait a moment for processing
sleep(3);

// Get stats after
$stats_after = AltText_AI_Queue::get_stats();
echo "\nAfter:\n";
echo "  Pending: " . $stats_after['pending'] . "\n";
echo "  Processing: " . $stats_after['processing'] . "\n";
echo "  Failed: " . $stats_after['failed'] . "\n";
echo "  Completed (24h): " . $stats_after['completed_recent'] . "\n\n";

$processed = $stats_before['pending'] - $stats_after['pending'];
if ($processed > 0) {
    echo "✓ Processed $processed job(s)\n";
} else {
    echo "⚠️  No jobs were processed. They may still be processing.\n";
    echo "   Check the dashboard to see if status changed to 'processing' or 'completed'.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

