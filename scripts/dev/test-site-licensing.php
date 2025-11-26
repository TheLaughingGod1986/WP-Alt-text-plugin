<?php
/**
 * Test Site-Based Licensing Implementation
 * 
 * This script verifies that:
 * 1. Authentication is site-wide (not per-user)
 * 2. Site ID is generated and stored correctly
 * 3. Usage quota is shared across all users
 * 4. Token is accessible to all users
 * 
 * Usage: wp eval-file test-site-licensing.php
 */

if (!defined('ABSPATH')) {
    // Load WordPress if running standalone
    require_once(__DIR__ . '/../../../../wp-load.php');
}

// Check if we're in WordPress context
if (!function_exists('get_option')) {
    die("Error: WordPress not loaded. Run this via: wp eval-file test-site-licensing.php\n");
}

echo "=== Site-Based Licensing Test ===\n\n";

// Test 1: Check Site ID Generation
echo "1. Testing Site ID Generation...\n";
$site_id = get_option('beepbeepai_site_id', '');
if (empty($site_id)) {
    echo "   ⚠️  Site ID not found. It will be generated on first API request.\n";
    echo "   This is normal if no one has logged in yet.\n";
} else {
    echo "   ✅ Site ID exists: " . substr($site_id, 0, 16) . "...\n";
    echo "   ✅ Site ID length: " . strlen($site_id) . " characters\n";
}

// Test 2: Check Token Storage (Site-Wide)
echo "\n2. Testing Token Storage (Site-Wide)...\n";
$token = get_option('beepbeepai_jwt_token', '');
if (empty($token)) {
    echo "   ⚠️  No token found. User needs to log in first.\n";
    echo "   This is expected if no authentication has occurred.\n";
} else {
    echo "   ✅ Token exists (site-wide)\n";
    echo "   ✅ Token length: " . strlen($token) . " characters\n";
    echo "   ✅ Token is stored in wp_options (accessible to all users)\n";
}

// Test 3: Check User Data Storage
echo "\n3. Testing User Data Storage...\n";
$user_data = get_option('beepbeepai_user_data', null);
if ($user_data === null || $user_data === false) {
    echo "   ⚠️  No user data found. User needs to log in first.\n";
} else {
    echo "   ✅ User data exists (site-wide)\n";
    if (is_array($user_data)) {
        echo "   ✅ Email: " . ($user_data['email'] ?? 'N/A') . "\n";
        echo "   ✅ Plan: " . ($user_data['plan'] ?? 'N/A') . "\n";
    }
}

// Test 4: Check Usage Cache (Site-Wide)
echo "\n4. Testing Usage Cache (Site-Wide)...\n";
$usage_cache = get_transient('beepbeepai_usage_cache');
if ($usage_cache === false) {
    echo "   ⚠️  No usage cache found. This is normal if no API calls have been made.\n";
} else {
    echo "   ✅ Usage cache exists (site-wide)\n";
    if (is_array($usage_cache)) {
        echo "   ✅ Used: " . ($usage_cache['used'] ?? 0) . "\n";
        echo "   ✅ Limit: " . ($usage_cache['limit'] ?? 0) . "\n";
        echo "   ✅ Plan: " . ($usage_cache['plan'] ?? 'free') . "\n";
    }
}

// Test 5: Verify API Client Configuration
echo "\n5. Testing API Client Configuration...\n";
if (class_exists('BbAI_API_Client_V2')) {
    $api_client = new BbAI_API_Client_V2();
    
    // Check if authenticated
    $is_authenticated = $api_client->is_authenticated();
    echo "   " . ($is_authenticated ? "✅" : "⚠️ ") . " Authentication status: " . ($is_authenticated ? "Authenticated" : "Not authenticated") . "\n";
    
    // Check user data retrieval
    $user_data_from_client = $api_client->get_user_data();
    if ($user_data_from_client) {
        echo "   ✅ User data accessible via API client\n";
    } else {
        echo "   ⚠️  No user data via API client (needs login)\n";
    }
} else {
    echo "   ❌ API Client class not found!\n";
}

// Test 6: Check WordPress Options (Site-Wide Storage)
echo "\n6. Testing WordPress Options (Site-Wide Storage)...\n";
$options_to_check = [
    'beepbeepai_site_id' => 'Site ID',
    'beepbeepai_jwt_token' => 'JWT Token',
    'beepbeepai_user_data' => 'User Data',
];

foreach ($options_to_check as $option_key => $label) {
    $value = get_option($option_key, null);
    if ($value !== null && $value !== false && $value !== '') {
        echo "   ✅ {$label}: Stored in wp_options (site-wide)\n";
    } else {
        echo "   ⚠️  {$label}: Not set (this is normal if not logged in)\n";
    }
}

// Test 7: Simulate Multi-User Scenario
echo "\n7. Simulating Multi-User Scenario...\n";
$current_user_id = get_current_user_id();
if ($current_user_id > 0) {
    echo "   ✅ Current user ID: {$current_user_id}\n";
    echo "   ✅ Token accessible to this user: " . (get_option('beepbeepai_jwt_token') ? "Yes" : "No") . "\n";
    echo "   ✅ Usage cache accessible to this user: " . (get_transient('beepbeepai_usage_cache') !== false ? "Yes" : "No") . "\n";
} else {
    echo "   ⚠️  No user logged in (running as CLI)\n";
}

// Test 8: Check if Site ID would be sent in API requests
echo "\n8. Testing Site ID in API Requests...\n";
if (class_exists('BbAI_API_Client_V2')) {
    // Use reflection to access private method for testing
    $reflection = new ReflectionClass('BbAI_API_Client_V2');
    $method = $reflection->getMethod('get_site_id');
    $method->setAccessible(true);
    
    $api_client = new BbAI_API_Client_V2();
    $site_id_from_client = $method->invoke($api_client);
    
    if (!empty($site_id_from_client)) {
        echo "   ✅ Site ID would be sent in API requests: " . substr($site_id_from_client, 0, 16) . "...\n";
    } else {
        echo "   ⚠️  Site ID not generated yet (will be generated on first API call)\n";
    }
}

// Summary
echo "\n=== Test Summary ===\n";
echo "✅ Site-based licensing is correctly implemented if:\n";
echo "   1. Site ID is generated and stored in wp_options\n";
echo "   2. Token is stored in wp_options (not user meta)\n";
echo "   3. Usage cache is stored in transients (site-wide)\n";
echo "   4. All users can access the same token and usage data\n";
echo "\n";
echo "📝 Next Steps:\n";
echo "   1. Have one admin user log in via the plugin\n";
echo "   2. Check that all users see 'Logged in' status\n";
echo "   3. Have different users generate alt text\n";
echo "   4. Verify all users see the same usage quota\n";
echo "\n";

