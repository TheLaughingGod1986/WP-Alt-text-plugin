<?php
/**
 * Check License Status
 *
 * Usage: wp eval-file check-license-status.php
 */

echo "🔍 Checking License Status\n\n";

// Check all relevant options
$license_key = get_option('optti_license_key', null);
$license_data = get_option('optti_license_data', null);
$jwt_token = get_option('optti_jwt_token', null);
$site_id = get_option('optti_site_id', null);

echo "📋 Database Values:\n";
echo "  optti_license_key: " . ($license_key !== null ? (strlen($license_key) > 0 ? "SET (length: " . strlen($license_key) . ")" : "EMPTY STRING") : "NOT SET") . "\n";
if ($license_key && strlen($license_key) > 0) {
    echo "    Preview: " . substr($license_key, 0, 20) . "...\n";
}
echo "  optti_license_data: " . ($license_data !== null ? "SET" : "NOT SET") . "\n";
if ($license_data) {
    echo "    Content: " . json_encode($license_data, JSON_PRETTY_PRINT) . "\n";
}
echo "  optti_jwt_token: " . ($jwt_token !== null ? (strlen($jwt_token) > 0 ? "SET" : "EMPTY") : "NOT SET") . "\n";
echo "  optti_site_id: " . ($site_id !== null ? $site_id : "NOT SET") . "\n";

echo "\n🔧 API Client Check:\n";
if (class_exists('BeepBeep\\AltText\\ApiClient')) {
    $api = BeepBeep\AltText\ApiClient::instance();
    $key = $api->get_license_key();
    echo "  get_license_key(): " . ($key ? "'" . substr($key, 0, 20) . "...'" : "EMPTY") . "\n";

    $site = $api->get_site_id();
    echo "  get_site_id(): " . ($site ? $site : "EMPTY") . "\n";
} else {
    echo "  ❌ ApiClient class not found\n";
}

echo "\n📊 Summary:\n";
if ($license_key && strlen($license_key) > 0) {
    echo "  ✅ License key is stored\n";
} else {
    echo "  ❌ License key is NOT stored\n";
    echo "  💡 This is why requests are failing with 401!\n";
}
