<?php
/**
 * Check queue status and manually process queue
 * 
 * Usage: php scripts/check-and-process-queue.php
 */

if (php_sapi_name() === 'cli') {
    $wp_load_paths = [
        dirname(__DIR__) . '/../../../../wp-load.php',
        '/var/www/html/wp-load.php',
        dirname(__DIR__) . '/../../../wp-load.php',
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
        die("Could not find wp-load.php\n");
    }
}

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

// Load the queue class
if (!class_exists('BbAI_Queue')) {
    require_once dirname(__DIR__) . '/includes/class-queue.php';
}

// WordPress plugins should be loaded by wp-load.php
// We'll use WordPress actions to trigger queue processing

echo "Queue Status Check\n";
echo "==================\n\n";

// Get queue stats
$stats = BbAI_Queue::get_stats();
echo "Current Queue Status:\n";
echo "  Pending: {$stats['pending']}\n";
echo "  Processing: {$stats['processing']}\n";
echo "  Failed: {$stats['failed']}\n";
echo "  Completed: {$stats['completed']}\n\n";

// Check if cron is scheduled
$next_scheduled = wp_next_scheduled(BbAI_Queue::CRON_HOOK);
if ($next_scheduled) {
    $time_until = $next_scheduled - time();
    echo "Next cron scheduled: " . date('Y-m-d H:i:s', $next_scheduled) . " (in {$time_until} seconds)\n";
} else {
    echo "⚠ No cron scheduled!\n";
}

echo "\n";

// Get pending jobs
$pending_jobs = BbAI_Queue::get_recent(10);
if (!empty($pending_jobs)) {
    echo "Recent jobs:\n";
    foreach ($pending_jobs as $job) {
        echo "  ID: {$job['id']}, Attachment: {$job['attachment_id']}, Status: {$job['status']}, Attempts: {$job['attempts']}\n";
        if (!empty($job['last_error'])) {
            echo "    Error: {$job['last_error']}\n";
        }
    }
    echo "\n";
}

// If there are pending jobs, process them
if ($stats['pending'] > 0 || $stats['processing'] > 0) {
    echo "Processing queue manually...\n";
    
    // Reset stale jobs first
    BbAI_Queue::reset_stale(10 * MINUTE_IN_SECONDS);
    echo "✓ Reset stale jobs\n";
    
    // Trigger the queue processing via WordPress action
    // This will call the process_queue method on the core class
    do_action(BbAI_Queue::CRON_HOOK);
    echo "✓ Queue processor executed\n\n";
    
    // Get updated stats
    $stats = BbAI_Queue::get_stats();
    echo "Updated Queue Status:\n";
    echo "  Pending: {$stats['pending']}\n";
    echo "  Processing: {$stats['processing']}\n";
    echo "  Failed: {$stats['failed']}\n";
    echo "  Completed: {$stats['completed']}\n\n";
    
    // Schedule next processing if there are still pending jobs
    if ($stats['pending'] > 0) {
        BbAI_Queue::schedule_processing(30);
        echo "✓ Scheduled next processing in 30 seconds\n";
    }
} else {
    echo "No jobs to process.\n";
}

echo "\nDone!\n";

