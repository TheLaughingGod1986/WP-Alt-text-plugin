<?php
/**
 * Check what credentials are stored in WordPress options
 *
 * Run this from WordPress root: wp eval-file wp-content/plugins/beepbeep-ai-alt-text-generator/check-stored-credentials.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "\n=== Checking BeepBeep AI Stored Credentials ===\n\n";

$options_to_check = [
    'optti_jwt_token' => 'JWT Token (new)',
    'beepbeepai_jwt_token' => 'JWT Token (legacy)',
    'optti_user_data' => 'User Data (new)',
    'beepbeepai_user_data' => 'User Data (legacy)',
    'optti_license_key' => 'License Key (new)',
    'beepbeepai_license_key' => 'License Key (legacy)',
    'optti_license_data' => 'License Data (new)',
    'beepbeepai_license_data' => 'License Data (legacy)',
];

foreach ($options_to_check as $option_key => $label) {
    $value = get_option($option_key);

    if (empty($value)) {
        echo "❌ {$label} ({$option_key}): NOT SET\n";
    } else {
        echo "✅ {$label} ({$option_key}): SET\n";

        // Show preview for some options
        if ($option_key === 'optti_jwt_token') {
            // Decrypt if encrypted
            if (strpos($value, 'enc:') === 0) {
                echo "   Type: Encrypted\n";
                echo "   Preview: " . substr($value, 0, 50) . "...\n";
            } else {
                echo "   Type: Plain text\n";
                $parts = explode('.', $value);
                if (count($parts) === 3) {
                    $payload = json_decode(base64_decode($parts[1]), true);
                    echo "   Expires: " . date('Y-m-d H:i:s', $payload['exp'] ?? 0) . "\n";
                    echo "   User ID: " . ($payload['user_id'] ?? 'N/A') . "\n";
                    echo "   Email: " . ($payload['email'] ?? 'N/A') . "\n";
                    echo "   License Key: " . ($payload['license_key'] ?? 'N/A') . "\n";
                }
            }
        } elseif ($option_key === 'optti_user_data') {
            if (is_array($value)) {
                echo "   Email: " . ($value['email'] ?? 'N/A') . "\n";
                echo "   License Key: " . ($value['license_key'] ?? 'NOT IN USER DATA') . "\n";
                echo "   Plan: " . ($value['plan'] ?? 'N/A') . "\n";
            }
        } elseif ($option_key === 'optti_license_key') {
            if (strpos($value, 'enc:') === 0) {
                echo "   Type: Encrypted\n";
            } else {
                echo "   Value: " . $value . "\n";
            }
        }
    }
    echo "\n";
}

echo "\n=== Summary ===\n";
$token = get_option('optti_jwt_token');
$license_key = get_option('optti_license_key');

if (!empty($token) && !empty($license_key)) {
    echo "✅ READY: Both JWT token and license key are set!\n";
    echo "   Login should work correctly.\n";
} elseif (!empty($token) && empty($license_key)) {
    echo "⚠️  ISSUE: JWT token is set but license key is NOT set.\n";
    echo "   This is the bug we're trying to fix.\n";
    echo "   The login code needs to save the license key from user data.\n";
} elseif (empty($token) && !empty($license_key)) {
    echo "⚠️  PARTIAL: License key is set but no JWT token.\n";
    echo "   User may have used license key activation instead of login.\n";
} else {
    echo "❌ NOT AUTHENTICATED: No credentials found.\n";
    echo "   User needs to log in or activate a license key.\n";
}

echo "\n";
