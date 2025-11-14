<?php
/**
 * Fix Queue Cron - Clear stuck cron and reschedule
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

echo "Fixing Queue Cron\n";
echo str_repeat("=", 50) . "\n\n";

// Clear any stuck cron events
$cleared = wp_clear_scheduled_hook(AltText_AI_Queue::CRON_HOOK);
echo "Cleared $cleared stuck cron event(s)\n\n";

// Schedule processing immediately (in 5 seconds)
AltText_AI_Queue::schedule_processing(5);

$next = wp_next_scheduled(AltText_AI_Queue::CRON_HOOK);
if ($next) {
    $seconds = $next - time();
    echo "✓ Cron rescheduled for " . date('Y-m-d H:i:s', $next) . " ($seconds seconds from now)\n\n";
} else {
    echo "✗ Failed to schedule cron\n\n";
}

// Get pending count
$stats = AltText_AI_Queue::get_stats();
echo "Pending jobs: " . $stats['pending'] . "\n\n";

// Try to trigger WordPress cron manually
echo "Triggering WordPress cron...\n";
spawn_cron();

echo "✓ Cron triggered\n\n";

echo "Note: WordPress cron runs when someone visits the site.\n";
echo "If jobs are still stuck, click 'Process queue now' in the dashboard.\n";

echo "\n" . str_repeat("=", 50) . "\n";


