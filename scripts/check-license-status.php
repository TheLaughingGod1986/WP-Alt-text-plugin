<?php
/**
 * Check License and Subscription Status
 * This script checks the WordPress plugin's license status and attempts to auto-attach if needed
 */

$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/var/www/html/wp-content/plugins/beepbeep-ai-alt-text-generator/../../../../wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

echo "🔍 Checking License and Subscription Status\n";
echo str_repeat("=", 60) . "\n\n";

// Load the API client
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new BbAI_API_Client_V2();

// Check authentication status
echo "1. Authentication Status:\n";
$is_authenticated = $api_client->is_authenticated();
echo "   " . ($is_authenticated ? "✅ Authenticated" : "❌ Not authenticated") . "\n";

if ($is_authenticated) {
    $token = $api_client->get_token();
    echo "   Token: " . (!empty($token) ? substr($token, 0, 20) . "..." : "None") . "\n";
    
    // Get user info
    $user_info = $api_client->get_user_info();
    if (is_wp_error($user_info)) {
        echo "   User Info Error: " . $user_info->get_error_message() . "\n";
    } else {
        echo "   User Email: " . ($user_info['email'] ?? 'N/A') . "\n";
        echo "   User ID: " . ($user_info['id'] ?? 'N/A') . "\n";
    }
} else {
    echo "   ⚠️  User is not authenticated. Please log in first.\n";
}

echo "\n";

// Check license status
echo "2. License Status:\n";
$has_license = $api_client->has_active_license();
echo "   " . ($has_license ? "✅ Has active license" : "❌ No active license") . "\n";

$license_key = $api_client->get_license_key();
echo "   License Key: " . (!empty($license_key) ? $license_key : "None") . "\n";

$license_data = $api_client->get_license_data();
if ($license_data) {
    echo "   License Data:\n";
    echo "     Plan: " . ($license_data['organization']['plan'] ?? 'N/A') . "\n";
    echo "     Organization: " . ($license_data['organization']['name'] ?? 'N/A') . "\n";
    if (isset($license_data['site'])) {
        echo "     Site Hash: " . ($license_data['site']['siteHash'] ?? 'N/A') . "\n";
    }
} else {
    echo "   No license data stored\n";
}

echo "\n";

// Check site fingerprint
echo "3. Site Information:\n";
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
$site_hash = \BeepBeepAI\AltTextGenerator\Site_Fingerprint::get_site_id();
echo "   Site Hash: " . $site_hash . "\n";
echo "   Site URL: " . get_site_url() . "\n";

echo "\n";

// Check framework snapshot (if available)
echo "4. Framework Snapshot:\n";
if (function_exists('opptiai_framework')) {
    $framework = opptiai_framework();
    if ($framework && isset($framework->licensing)) {
        $snapshot = $framework->licensing->get_snapshot();
        if (!empty($snapshot)) {
            echo "   ✅ Framework snapshot found:\n";
            echo "     Plan: " . ($snapshot['plan'] ?? 'N/A') . "\n";
            echo "     License Key: " . (!empty($snapshot['licenseKey']) ? substr($snapshot['licenseKey'], 0, 20) . "..." : 'N/A') . "\n";
        } else {
            echo "   ❌ No framework snapshot\n";
        }
    } else {
        echo "   ⚠️  Framework licensing not available\n";
    }
} else {
    echo "   ⚠️  Framework function not available\n";
}

echo "\n";

// Attempt auto-attach if no license
if (!$has_license && $is_authenticated) {
    echo "5. Attempting Auto-Attach License:\n";
    echo "   Attempting to auto-attach license for free plan...\n";
    
    $auto_attach_result = $api_client->auto_attach_license();
    
    if (is_wp_error($auto_attach_result)) {
        echo "   ❌ Auto-attach failed:\n";
        echo "      Error: " . $auto_attach_result->get_error_message() . "\n";
        $error_data = $auto_attach_result->get_error_data();
        if (isset($error_data['is_schema_error']) && $error_data['is_schema_error']) {
            echo "      ⚠️  This appears to be a backend database schema issue\n";
        }
    } else {
        echo "   ✅ Auto-attach succeeded!\n";
        if (isset($auto_attach_result['license'])) {
            echo "      Plan: " . ($auto_attach_result['license']['plan'] ?? 'N/A') . "\n";
            echo "      Token Limit: " . ($auto_attach_result['license']['tokenLimit'] ?? 'N/A') . "\n";
        }
        
        // Re-check license status
        $has_license_after = $api_client->has_active_license();
        echo "   License status after auto-attach: " . ($has_license_after ? "✅ Active" : "❌ Still inactive") . "\n";
    }
} else {
    echo "5. Auto-Attach:\n";
    if (!$is_authenticated) {
        echo "   ⚠️  Skipped - user not authenticated\n";
    } else {
        echo "   ⚠️  Skipped - license already exists\n";
    }
}

echo "\n";

// Check usage
echo "6. Usage Status:\n";
$usage = $api_client->get_usage();
if (is_wp_error($usage)) {
    echo "   ❌ Error getting usage: " . $usage->get_error_message() . "\n";
    $error_code = $usage->get_error_code();
    if ($error_code === 'no_access') {
        echo "   ⚠️  This is the 'no_access' error you're seeing!\n";
        $error_data = $usage->get_error_data();
        if (isset($error_data['reason'])) {
            echo "      Reason: " . $error_data['reason'] . "\n";
        }
    }
} else {
    echo "   ✅ Usage retrieved:\n";
    echo "      Used: " . ($usage['used'] ?? 0) . "\n";
    echo "      Limit: " . ($usage['limit'] ?? 0) . "\n";
    echo "      Remaining: " . ($usage['remaining'] ?? 0) . "\n";
    echo "      Plan: " . ($usage['plan'] ?? 'N/A') . "\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Summary:\n";
echo "  - Authenticated: " . ($is_authenticated ? "Yes" : "No") . "\n";
echo "  - Has License: " . ($has_license ? "Yes" : "No") . "\n";
echo "  - Can Generate: " . ($has_license && $is_authenticated ? "Yes" : "No") . "\n";

if (!$has_license && $is_authenticated) {
    echo "\n⚠️  ISSUE DETECTED: User is authenticated but has no license.\n";
    echo "   This is likely why you're getting 'no subscription' errors.\n";
    echo "   The auto-attach should have been attempted above.\n";
    echo "   If it failed, check the backend database for:\n";
    echo "   1. User record exists\n";
    echo "   2. License record exists for the user's organization\n";
    echo "   3. Site record exists and is linked to the license\n";
    echo "\n   Run scripts/check-subscription-status.sql in your database to investigate.\n";
}

echo "\n";

