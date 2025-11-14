<?php
/**
 * Debug Limit Check - Find out why generation is being blocked
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

echo "🔍 Debugging Limit Check Issues\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Check API usage
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new AltText_AI_API_Client_V2();

echo "1. API Client Status:\n";
echo "   Authenticated: " . ($api_client->is_authenticated() ? 'YES' : 'NO') . "\n";

$usage = $api_client->get_usage();
if (is_wp_error($usage)) {
    echo "   ❌ Error getting usage: " . $usage->get_error_message() . "\n";
    echo "   Error Code: " . $usage->get_error_code() . "\n";
} else {
    echo "   ✓ Usage Data:\n";
    echo "     Used: " . ($usage['used'] ?? 'N/A') . "\n";
    echo "     Limit: " . ($usage['limit'] ?? 'N/A') . "\n";
    echo "     Remaining: " . ($usage['remaining'] ?? 'N/A') . "\n";
    echo "     Plan: " . ($usage['plan'] ?? 'N/A') . "\n";
}

echo "\n";

// 2. Check has_reached_limit
echo "2. has_reached_limit() Check:\n";
$has_reached = $api_client->has_reached_limit();
echo "   Result: " . ($has_reached ? 'TRUE (BLOCKED)' : 'FALSE (ALLOWED)') . "\n";

if ($has_reached && is_array($usage)) {
    $used = intval($usage['used'] ?? 0);
    $limit = intval($usage['limit'] ?? 50);
    $remaining = intval($usage['remaining'] ?? 50);
    echo "   ⚠️  This is wrong! Used={$used}, Limit={$limit}, Remaining={$remaining}\n";
    echo "   Expected: FALSE (because used={$used} < limit={$limit})\n";
}

echo "\n";

// 3. Check usage tracker
echo "3. Usage Tracker Cache:\n";
$cached = AltText_AI_Usage_Tracker::get_cached_usage();
echo "   Cached Used: " . ($cached['used'] ?? 'N/A') . "\n";
echo "   Cached Limit: " . ($cached['limit'] ?? 'N/A') . "\n";
echo "   Cached Remaining: " . ($cached['remaining'] ?? 'N/A') . "\n";

$stats = AltText_AI_Usage_Tracker::get_stats_display();
echo "   Stats Display Used: " . ($stats['used'] ?? 'N/A') . "\n";
echo "   Stats Display Limit: " . ($stats['limit'] ?? 'N/A') . "\n";
echo "   Stats Display Remaining: " . ($stats['remaining'] ?? 'N/A') . "\n";

echo "\n";

// 4. Check usage governance
echo "4. Usage Governance Check:\n";
global $alttextai_plugin;
if (isset($alttextai_plugin) && isset($alttextai_plugin->usage_governance)) {
    $gov = $alttextai_plugin->usage_governance;
    $plan = $stats['plan'] ?? 'free';
    $used = $stats['used'] ?? 0;
    
    echo "   Plan: {$plan}\n";
    echo "   Used: {$used}\n";
    
    if (method_exists($gov, 'should_block_generation')) {
        $should_block = $gov->should_block_generation($plan, $used);
        echo "   should_block_generation(): " . ($should_block ? 'TRUE (BLOCKS)' : 'FALSE (ALLOWS)') . "\n";
        
        if ($should_block && $used == 0) {
            echo "   ⚠️  PROBLEM: should_block_generation() returns TRUE when used=0!\n";
        }
    } else {
        echo "   Method not found\n";
    }
} else {
    echo "   Usage governance not initialized\n";
}

echo "\n";

// 5. Check if API is returning 429 error
echo "5. Testing API Generate Endpoint:\n";
// Don't actually generate, just check what would happen

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Summary:\n";
if ($has_reached && ($usage['used'] ?? 0) == 0) {
    echo "❌ ISSUE FOUND: has_reached_limit() returns TRUE when usage is 0!\n";
    echo "   This is the bug causing the block.\n";
} elseif (isset($gov) && method_exists($gov, 'should_block_generation') && $gov->should_block_generation($plan, $used) && $used == 0) {
    echo "❌ ISSUE FOUND: should_block_generation() returns TRUE when usage is 0!\n";
    echo "   This is the bug causing the block.\n";
} else {
    echo "✓ Limit checks appear correct. The block might be coming from:\n";
    echo "  - API returning 429 error\n";
    echo "  - Rate limiting\n";
    echo "  - Other error in generation flow\n";
}
echo "\n";
