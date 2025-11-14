<?php
/**
 * Check Backend Database Usage Count
 * This will show the actual count from alttext-ai-db if direct DB access is configured
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

$user_email = isset($argv[1]) ? $argv[1] : 'benoats@gmail.com';

echo "Checking Backend Database Usage for: {$user_email}\n";
echo str_repeat("=", 60) . "\n\n";

// Method 1: Check API (source of truth)
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new AltText_AI_API_Client_V2();

echo "1. From Backend API (Source of Truth):\n";
$usage = $api_client->get_usage();
if (is_wp_error($usage)) {
    echo "   ❌ Error: " . $usage->get_error_message() . "\n";
} else {
    echo "   Used: " . ($usage['used'] ?? 0) . "\n";
    echo "   Limit: " . ($usage['limit'] ?? 50) . "\n";
    echo "   Remaining: " . ($usage['remaining'] ?? 50) . "\n";
    echo "   Plan: " . ($usage['plan'] ?? 'free') . "\n";
    
    if (($usage['used'] ?? 0) >= ($usage['limit'] ?? 50)) {
        echo "   ⚠️  LIMIT REACHED - This is why generation is blocked!\n";
    }
}
echo "\n";

// Method 2: Try direct database if configured
if (defined('ALTTEXT_AI_DB_ENABLED') && ALTTEXT_AI_DB_ENABLED) {
    if (class_exists('AltText_AI_Direct_DB_Usage')) {
        echo "2. From Direct Database Access:\n";
        $db_usage = AltText_AI_Direct_DB_Usage::get_usage_from_db($user_email);
        if (!is_wp_error($db_usage)) {
            echo "   Used: " . ($db_usage['used'] ?? 0) . "\n";
            echo "   Limit: " . ($db_usage['limit'] ?? 50) . "\n";
            echo "   Remaining: " . ($db_usage['remaining'] ?? 50) . "\n";
            
            if (($db_usage['used'] ?? 0) != ($usage['used'] ?? 0)) {
                echo "   ⚠️  MISMATCH: API says " . ($usage['used'] ?? 0) . " but DB says " . ($db_usage['used'] ?? 0) . "\n";
            }
        } else {
            echo "   Direct DB access not available: " . $db_usage->get_error_message() . "\n";
        }
        echo "\n";
    }
}

echo str_repeat("=", 60) . "\n";
echo "Conclusion:\n";
if (($usage['used'] ?? 0) >= ($usage['limit'] ?? 50)) {
    echo "❌ The backend API reports you've used all " . ($usage['limit'] ?? 50) . " generations.\n";
    echo "   WordPress is correctly blocking generation because the limit is reached.\n";
    echo "\n";
    echo "To fix this:\n";
    echo "1. Check if other WordPress sites are using this account\n";
    echo "2. Check the backend database (alttext-ai-db) for the actual count\n";
    echo "3. Contact backend support to reset usage if it's incorrect\n";
    echo "4. Wait until " . ($usage['resetDate'] ?? 'next month') . " for automatic reset\n";
} else {
    echo "✓ Usage is below limit - generation should work.\n";
}
echo "\n";
