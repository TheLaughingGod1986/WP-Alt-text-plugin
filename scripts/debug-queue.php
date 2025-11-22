<?php
/**
 * Debug Queue Status
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

echo "Queue Debug Information\n";
echo str_repeat("=", 50) . "\n\n";

// Get queue stats
$stats = BbAI_Queue::get_stats();
echo "Queue Status:\n";
echo "  Pending: " . $stats['pending'] . "\n";
echo "  Processing: " . $stats['processing'] . "\n";
echo "  Failed: " . $stats['failed'] . "\n";
echo "  Completed (24h): " . $stats['completed_recent'] . "\n\n";

// Check if cron is scheduled
$next_cron = wp_next_scheduled(BbAI_Queue::CRON_HOOK);
echo "Cron Status:\n";
if ($next_cron) {
    $time_until = $next_cron - time();
    echo "  Next scheduled: " . date('Y-m-d H:i:s', $next_cron) . " (" . $time_until . " seconds from now)\n";
} else {
    echo "  ⚠️  Cron NOT scheduled - this is the problem!\n";
}
echo "\n";

// Get pending jobs
global $wpdb;
$table = BbAI_Queue::table();
$pending = $wpdb->get_results(
    "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY id ASC LIMIT 5",
    ARRAY_A
);

if ($pending) {
    echo "Pending Jobs:\n";
    foreach ($pending as $job) {
        $age = time() - strtotime($job['enqueued_at']);
        echo "  ID: {$job['id']}, Attachment: {$job['attachment_id']}, Age: {$age} seconds\n";
    }
    echo "\n";
    
    // Check if cron is disabled
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        echo "⚠️  WP_CRON is DISABLED via DISABLE_WP_CRON constant\n";
        echo "   WordPress cron will not run automatically.\n\n";
    }
    
    // Try to schedule processing now
    echo "Attempting to trigger processing...\n";
    BbAI_Queue::schedule_processing(5); // Schedule in 5 seconds
    
    $next_cron = wp_next_scheduled(BbAI_Queue::CRON_HOOK);
    if ($next_cron) {
        echo "✓ Scheduled for: " . date('Y-m-d H:i:s', $next_cron) . "\n";
    } else {
        echo "✗ Failed to schedule\n";
    }
    
    echo "\n";
    echo "To manually trigger processing, you can:\n";
    echo "1. Click 'Process queue now' in the dashboard\n";
    echo "2. Or wait for WordPress cron to run (usually every 5 minutes)\n";
    echo "3. Or trigger via: wp cron event run beepbeepai_process_queue\n";
} else {
    echo "No pending jobs found.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";


