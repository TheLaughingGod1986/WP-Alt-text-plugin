<?php
/**
 * Check License Key Storage
 * This script checks all possible locations where the license key might be stored
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

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

echo "🔑 Checking License Key Storage\n";
echo str_repeat("=", 60) . "\n\n";

// Load the API client
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new BbAI_API_Client_V2();

// 1. Check WordPress option directly
echo "1. WordPress Option (beepbeepai_license_key):\n";
$option_key = get_option('beepbeepai_license_key', '');
if (!empty($option_key)) {
    // Try to decrypt
    if (method_exists($api_client, 'maybe_decrypt_secret')) {
        $reflection = new ReflectionClass($api_client);
        $method = $reflection->getMethod('maybe_decrypt_secret');
        $method->setAccessible(true);
        $decrypted = $method->invoke($api_client, $option_key);
        if (!empty($decrypted)) {
            echo "   ✅ Found (encrypted): " . $decrypted . "\n";
        } else {
            echo "   ⚠️  Found but couldn't decrypt: " . substr($option_key, 0, 20) . "...\n";
        }
    } else {
        echo "   ⚠️  Found: " . substr($option_key, 0, 20) . "...\n";
    }
} else {
    echo "   ❌ Not found\n";
}

echo "\n";

// 2. Check via get_license_key() method
echo "2. API Client get_license_key():\n";
$license_key = $api_client->get_license_key();
if (!empty($license_key)) {
    echo "   ✅ Found: " . $license_key . "\n";
} else {
    echo "   ❌ Not found\n";
}

echo "\n";

// 3. Check framework snapshot
echo "3. Framework Snapshot:\n";
if (function_exists('opptiai_framework')) {
    $framework = opptiai_framework();
    if ($framework && isset($framework->licensing)) {
        $snapshot = $framework->licensing->get_snapshot();
        if (!empty($snapshot) && isset($snapshot['licenseKey'])) {
            echo "   ✅ Found: " . $snapshot['licenseKey'] . "\n";
        } else {
            echo "   ❌ Not found in snapshot\n";
            if (!empty($snapshot)) {
                echo "   Snapshot keys: " . implode(', ', array_keys($snapshot)) . "\n";
            }
        }
    } else {
        echo "   ⚠️  Framework licensing not available\n";
    }
} else {
    echo "   ⚠️  Framework function not available\n";
}

echo "\n";

// 4. Check license data
echo "4. License Data:\n";
$license_data = $api_client->get_license_data();
if (!empty($license_data)) {
    // Check top-level
    if (isset($license_data['licenseKey']) && !empty($license_data['licenseKey'])) {
        echo "   ✅ Found in top-level: " . $license_data['licenseKey'] . "\n";
    } else {
        echo "   ❌ Not found in top-level\n";
    }
    
    // Check site data
    if (isset($license_data['site']) && is_array($license_data['site'])) {
        if (isset($license_data['site']['licenseKey']) && !empty($license_data['site']['licenseKey'])) {
            echo "   ✅ Found in site data: " . $license_data['site']['licenseKey'] . "\n";
        } else {
            echo "   ❌ Not found in site data\n";
            if (!empty($license_data['site'])) {
                echo "   Site data keys: " . implode(', ', array_keys($license_data['site'])) . "\n";
            }
        }
    } else {
        echo "   ⚠️  No site data in license data\n";
    }
    
    // Show license data structure
    echo "   License data keys: " . implode(', ', array_keys($license_data)) . "\n";
} else {
    echo "   ❌ No license data stored\n";
}

echo "\n";

// 5. Summary
echo str_repeat("=", 60) . "\n";
echo "Summary:\n";
$final_key = $api_client->get_license_key();
if (!empty($final_key)) {
    echo "  ✅ License key is retrievable: " . $final_key . "\n";
    echo "  ✅ Should appear in settings page input field\n";
} else {
    echo "  ❌ License key is NOT retrievable\n";
    echo "  ❌ Will NOT appear in settings page input field\n";
    echo "\n";
    echo "  To fix this:\n";
    echo "  1. Try auto-attach: The plugin should auto-attach on login\n";
    echo "  2. Check backend: Verify the license exists in the backend database\n";
    echo "  3. Manual entry: Enter the license key manually in the settings page\n";
}

echo "\n";

