<?php
/**
 * Fix and migrate BeepBeep AI credentials to unified option keys
 *
 * Run this from WordPress root:
 * wp eval-file wp-content/plugins/beepbeep-ai-alt-text-generator/scripts/fix-credentials.php
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

echo "\n=== BeepBeep AI Credential Migration & Fix Script ===\n\n";

// Define migration mappings
$migrations = array(
    'beepbeepai_jwt_token' => 'optti_jwt_token',
    'bbai_jwt_token' => 'optti_jwt_token',
    'beepbeepai_license_key' => 'optti_license_key',
    'beepbeepai_license_data' => 'optti_license_data',
    'beepbeepai_user_data' => 'optti_user_data',
    'beepbeepai_site_id' => 'optti_site_id',
);

echo "Step 1: Checking and migrating legacy options...\n\n";

$migrated_count = 0;
foreach ($migrations as $legacy_key => $new_key) {
    $new_value = get_option($new_key, '');
    $legacy_value = get_option($legacy_key, '');
    
    echo "  {$legacy_key} → {$new_key}\n";
    echo "    Legacy: " . (empty($legacy_value) ? "❌ NOT SET" : "✅ SET") . "\n";
    echo "    New:    " . (empty($new_value) ? "❌ NOT SET" : "✅ SET") . "\n";
    
    if (empty($new_value) && !empty($legacy_value)) {
        update_option($new_key, $legacy_value, false);
        echo "    → ✨ MIGRATED legacy value to new key\n";
        $migrated_count++;
    } elseif (!empty($new_value) && !empty($legacy_value)) {
        echo "    → Both set, keeping new value\n";
    }
    echo "\n";
}

echo "Step 2: Current credential status...\n\n";

$credentials = array(
    'optti_jwt_token' => 'JWT Token',
    'optti_license_key' => 'License Key',
    'optti_license_data' => 'License Data',
    'optti_user_data' => 'User Data',
    'optti_site_id' => 'Site ID',
);

$all_set = true;
foreach ($credentials as $key => $label) {
    $value = get_option($key, '');
    $status = empty($value) ? "❌ NOT SET" : "✅ SET";
    if (empty($value)) {
        $all_set = false;
    }
    echo "  {$label} ({$key}): {$status}\n";
    
    // Show preview for tokens
    if (!empty($value) && $key === 'optti_jwt_token') {
        if (strpos($value, 'enc:') === 0) {
            echo "    Type: Encrypted\n";
        } else {
            echo "    Type: Plain JWT\n";
            $parts = explode('.', $value);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                if ($payload) {
                    $exp = $payload['exp'] ?? 0;
                    $now = time();
                    $expired = $exp < $now;
                    echo "    Expires: " . date('Y-m-d H:i:s', $exp) . ($expired ? " ⚠️ EXPIRED" : " ✅ Valid") . "\n";
                    echo "    User ID: " . ($payload['user_id'] ?? $payload['sub'] ?? 'N/A') . "\n";
                }
            }
        }
    }
    
    if (!empty($value) && $key === 'optti_license_key') {
        if (strpos($value, 'enc:') === 0) {
            echo "    Type: Encrypted\n";
        } else {
            echo "    Preview: " . substr($value, 0, 8) . "..." . substr($value, -4) . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Migrated: {$migrated_count} option(s)\n";

$token = get_option('optti_jwt_token', '');
$license_key = get_option('optti_license_key', '');

if (!empty($token) && !empty($license_key)) {
    echo "Status: ✅ READY - Both JWT token and license key are set\n";
} elseif (!empty($license_key) && empty($token)) {
    echo "Status: ✅ READY - License key is set (agency/license-based auth)\n";
} elseif (!empty($token) && empty($license_key)) {
    echo "Status: ⚠️ PARTIAL - JWT token is set but no license key\n";
    echo "        This may cause issues with license-based endpoints.\n";
    echo "        Try re-logging in or activating your license.\n";
} else {
    echo "Status: ❌ NOT AUTHENTICATED - No credentials found\n";
    echo "        User needs to log in or activate a license key.\n";
}

echo "\n";

