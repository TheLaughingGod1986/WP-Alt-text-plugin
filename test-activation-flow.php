<?php
/**
 * Test License Activation Flow
 *
 * Usage: wp eval-file test-activation-flow.php
 */

use BeepBeep\AltText\ApiClient;
use BeepBeep\AltText\LicenseManager;

echo "🧪 Testing License Activation Flow\n\n";

$license_key = '24c93235-1053-4922-b337-9866aeb76dcc';

// Step 1: Check current license status
echo "1️⃣ Checking current license status...\n";
$current_key = get_option('optti_license_key', '');
echo "   Current license key: " . ($current_key ?: 'NOT SET') . "\n\n";

// Step 2: Get API client and license manager instances
$api = ApiClient::instance();
$license_manager = LicenseManager::instance();

echo "2️⃣ Getting site fingerprint...\n";
$fingerprint = $license_manager->get_fingerprint();
echo "   Site fingerprint: $fingerprint\n\n";

echo "3️⃣ Activating license...\n";
echo "   License key: $license_key\n";
echo "   Site ID: " . $api->get_site_id() . "\n";
echo "   Site URL: " . get_site_url() . "\n";

$result = $api->activate_license($license_key, $fingerprint);

if (is_wp_error($result)) {
    echo "   ❌ Error: " . $result->get_error_message() . "\n";
    echo "   Error code: " . $result->get_error_code() . "\n";
    exit(1);
}

echo "   ✅ Success!\n";
echo "   Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Step 4: Save license key and data
echo "4️⃣ Saving license data...\n";
if (isset($result['license'])) {
    $api->set_license_key($license_key);
    $api->set_license_data($result['license']);
    echo "   ✅ License key saved\n";
    echo "   ✅ License data saved\n";
} else {
    echo "   ⚠️  No license data in response\n";
}

// Step 5: Verify storage
echo "\n5️⃣ Verifying storage...\n";
$stored_key = get_option('optti_license_key', '');
$stored_data = get_option('optti_license_data', null);
echo "   Stored license key: " . ($stored_key ?: 'NOT SET') . "\n";
echo "   Stored license data: " . ($stored_data ? 'SET' : 'NOT SET') . "\n";

if ($stored_data) {
    echo "   License plan: " . ($stored_data['plan'] ?? 'unknown') . "\n";
    echo "   License status: " . ($stored_data['status'] ?? 'unknown') . "\n";
}

echo "\n✅ License activation flow complete!\n";
