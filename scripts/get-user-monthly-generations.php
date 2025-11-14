<?php
/**
 * Get User Monthly Generation Count
 * Shows how many images a user has generated alt text for this month
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/var/www/html/wp-content/plugins/opptiai-alt/../../../../wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Get user email from command line or use default
$user_email = isset($argv[1]) ? $argv[1] : null;

// Try to get from stored user data
if (!$user_email) {
    $user_data = get_option('alttextai_user_data', null);
    if (is_array($user_data) && isset($user_data['email'])) {
        $user_email = $user_data['email'];
    }
}

// Also try to get from API client if available
if (!$user_email) {
    require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
    $api_client = new AltText_AI_API_Client_V2();
    $user_data = $api_client->get_user_data();
    if (is_array($user_data) && isset($user_data['email'])) {
        $user_email = $user_data['email'];
    }
}

if (!$user_email) {
    die("Error: User email required. Usage: php get-user-monthly-generations.php [email]\n");
}

echo "Monthly Generation Count for: {$user_email}\n";
echo str_repeat("=", 50) . "\n\n";

// Method 1: Get from cached usage (from API)
$usage_stats = AltText_AI_Usage_Tracker::get_stats_display();
echo "📊 From API Cache:\n";
echo "  Generations Used: " . ($usage_stats['used'] ?? 0) . "\n";
echo "  Limit: " . ($usage_stats['limit'] ?? 50) . "\n";
echo "  Remaining: " . ($usage_stats['remaining'] ?? 50) . "\n";
echo "  Plan: " . ($usage_stats['plan'] ?? 'free') . "\n";
echo "  Reset Date: " . ($usage_stats['reset_date'] ?? 'N/A') . "\n";
echo "\n";

// Method 2: Get fresh from API
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new AltText_AI_API_Client_V2();

if ($api_client->is_authenticated()) {
    echo "🔄 Fetching fresh data from API...\n";
    $live_usage = $api_client->get_usage();
    if (is_array($live_usage) && !empty($live_usage)) {
        AltText_AI_Usage_Tracker::update_usage($live_usage);
        $fresh_stats = AltText_AI_Usage_Tracker::get_stats_display(true);
        echo "  ✓ Fresh Data Retrieved:\n";
        echo "    Generations Used: " . ($fresh_stats['used'] ?? 0) . "\n";
        echo "    Limit: " . ($fresh_stats['limit'] ?? 50) . "\n";
        echo "    Remaining: " . ($fresh_stats['remaining'] ?? 50) . "\n";
        echo "    Plan: " . ($fresh_stats['plan'] ?? 'free') . "\n";
        echo "    Reset Date: " . ($fresh_stats['reset_date'] ?? 'N/A') . "\n";
        // Update the main usage stats with fresh data
        $usage_stats = $fresh_stats;
    } else {
        if (is_wp_error($live_usage)) {
            echo "  ✗ API Error: " . $live_usage->get_error_message() . "\n";
        } else {
            echo "  ✗ Failed to fetch from API (no data returned)\n";
        }
    }
    echo "\n";
} else {
    echo "⚠️  Not authenticated - cannot fetch fresh data from API\n";
    echo "   (Showing cached data only)\n\n";
}

// Method 3: Count from local WordPress database (if usage event tracker is active)
global $wpdb;
$events_table = $wpdb->prefix . 'alttextai_usage_events';
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t 23:59:59');

// Check if events table exists
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME,
    $events_table
));

if ($table_exists) {
    // Get install ID for this WordPress site
    $install_id = get_option('alttextai_install_id', '');
    if ($install_id) {
        $local_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT event_id) 
             FROM {$events_table} 
             WHERE install_id = %s 
             AND created_at >= %s 
             AND created_at <= %s",
            $install_id,
            $current_month_start,
            $current_month_end
        ));
        
        echo "💾 From Local WordPress Database:\n";
        echo "  Events This Month: " . intval($local_count) . "\n";
        echo "  (Note: This counts events on THIS WordPress site only)\n";
        echo "\n";
    }
}

// Method 4: Try direct database access if enabled
if (defined('ALTTEXT_AI_DB_ENABLED') && ALTTEXT_AI_DB_ENABLED) {
    if (class_exists('AltText_AI_Direct_DB_Usage')) {
        echo "🗄️  Fetching from Backend Database (if configured)...\n";
        $db_usage = AltText_AI_Direct_DB_Usage::get_usage_from_db($user_email);
        if (!is_wp_error($db_usage) && is_array($db_usage)) {
            echo "  ✓ Direct Database Data:\n";
            echo "    Generations Used: " . ($db_usage['used'] ?? 0) . "\n";
            echo "    Limit: " . ($db_usage['limit'] ?? 50) . "\n";
            echo "    Remaining: " . ($db_usage['remaining'] ?? 50) . "\n";
            echo "    Plan: " . ($db_usage['plan'] ?? 'free') . "\n";
        } else {
            echo "  ✗ Direct DB access not available or failed: " . $db_usage->get_error_message() . "\n";
        }
        echo "\n";
    }
}

echo str_repeat("=", 50) . "\n";
echo "Summary:\n";
echo "The user has generated alt text for " . ($usage_stats['used'] ?? 0) . " images this month.\n";
echo "This data comes from the backend API and is the authoritative source.\n";
echo "\n";
