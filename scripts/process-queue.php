<?php
/**
 * Process Queue Manually
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

require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-queue.php';

echo "Processing Queue\n";
echo str_repeat("=", 50) . "\n\n";

// Get queue stats
$stats = AltText_AI_Queue::get_stats();
echo "Queue Status:\n";
echo "  Pending: " . $stats['pending'] . "\n";
echo "  Processing: " . $stats['processing'] . "\n";
echo "  Failed: " . $stats['failed'] . "\n";
echo "  Completed (24h): " . $stats['completed_recent'] . "\n\n";

if ($stats['pending'] > 0) {
    echo "Processing pending jobs...\n";
    
    // Process the queue
    // This will trigger the background processing
    do_action('alttextai_process_queue');
    
    echo "✓ Queue processing triggered\n";
    echo "\nNote: Jobs are processed in the background.\n";
    echo "Refresh the dashboard to see updated status.\n";
} else {
    echo "No pending jobs to process.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";


