<?php
/**
 * Activate License Directly
 *
 * This bypasses the UI and activates the license directly via the backend API
 *
 * Usage: wp eval-file activate-license-direct.php
 */

$license_key = '24c93235-1053-4922-b337-9866aeb76dcc';

echo "🔑 Activating license: $license_key\n\n";

// Make direct API call to backend
$site_url = get_site_url();
$site_hash = md5($site_url);

$response = wp_remote_post('https://alttext-ai-backend.onrender.com/license/activate', [
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode([
        'license_key' => $license_key,
        'site_id' => $site_hash,
        'site_url' => $site_url,
        'site_name' => get_bloginfo('name'),
    ]),
    'timeout' => 30,
]);

if (is_wp_error($response)) {
    echo "❌ Error: " . $response->get_error_message() . "\n";
    exit(1);
}

$status = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);

echo "Status: $status\n";
echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

if ($status === 200 && isset($data['success']) && $data['success']) {
    echo "✅ License activated successfully!\n\n";

    // Save to WordPress database in the correct format
    update_option('optti_license_key', $license_key);

    $license_data = [
        'organization' => $data['license'],
        'site' => $data['site'],
    ];
    update_option('optti_license_data', $license_data);

    echo "✅ License key saved to database\n";
    echo "✅ License data saved to database\n\n";

    echo "📊 License Info:\n";
    echo "  Plan: " . ($data['license']['plan'] ?? 'unknown') . "\n";
    echo "  Status: " . ($data['license']['status'] ?? 'unknown') . "\n";
    echo "  Site: " . ($data['site']['site_url'] ?? 'unknown') . "\n";

    echo "\n🎉 All done! You can now use the plugin to generate alt text.\n";
} else {
    echo "❌ Activation failed!\n";
    echo "Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    exit(1);
}
