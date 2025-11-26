#!/usr/bin/env php
<?php
/**
 * Test Alt Text Generation Flow
 * Comprehensive diagnostic to identify why generation is failing
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    '/var/www/html/wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('ABSPATH')) {
    echo "❌ Failed to load WordPress\n";
    exit(1);
}

echo "Alt Text Generation Diagnostic\n";
echo str_repeat("=", 60) . "\n\n";

// Step 1: Check authentication
echo "1. Checking authentication...\n";
$token = get_option('beepbeepai_jwt_token', '');
if (empty($token)) {
    echo "   ❌ No JWT token found - user not authenticated\n";
    exit(1);
}
echo "   ✓ JWT token found\n\n";

// Step 2: Check API client
echo "2. Checking API client...\n";
if (!class_exists('BbAI_API_Client_V2')) {
    echo "   ❌ API Client class not found\n";
    exit(1);
}

$api_client = new BbAI_API_Client_V2();
echo "   ✓ API Client instantiated\n";

$is_authenticated = $api_client->is_authenticated();
echo "   Authentication status: " . ($is_authenticated ? "✓ Authenticated" : "❌ Not authenticated") . "\n\n";

// Step 3: Check usage limits
echo "3. Checking usage limits...\n";
$usage = $api_client->get_usage();
if (is_wp_error($usage)) {
    echo "   ❌ Failed to get usage: " . $usage->get_error_message() . "\n";
} else {
    echo "   ✓ Usage retrieved successfully\n";
    echo "   Used: " . ($usage['used'] ?? 'N/A') . "\n";
    echo "   Limit: " . ($usage['limit'] ?? 'N/A') . "\n";
    echo "   Remaining: " . ($usage['remaining'] ?? 'N/A') . "\n";

    if (($usage['remaining'] ?? 0) <= 0) {
        echo "   ⚠️  WARNING: No remaining usage credits\n";
    }
}
echo "\n";

// Step 4: Find a test image
echo "4. Finding a test image...\n";
$args = [
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'posts_per_page' => 1,
    'post_status' => 'inherit',
];
$images = get_posts($args);

if (empty($images)) {
    echo "   ❌ No images found in media library\n";
    exit(1);
}

$test_image = $images[0];
$image_id = $test_image->ID;
$image_url = wp_get_attachment_url($image_id);
$image_title = get_the_title($image_id);

echo "   ✓ Test image found\n";
echo "   ID: $image_id\n";
echo "   Title: $image_title\n";
echo "   URL: " . substr($image_url, 0, 60) . "...\n\n";

// Step 5: Test image payload preparation
echo "5. Testing image payload preparation...\n";
try {
    $reflection = new ReflectionClass($api_client);
    $method = $reflection->getMethod('prepare_image_payload');
    $method->setAccessible(true);

    $payload = $method->invoke($api_client, $image_id, $image_url, $image_title, '', wp_basename($image_url));
    echo "   ✓ Image payload prepared\n";
    echo "   Payload size: " . strlen(json_encode($payload)) . " bytes\n";
    if (isset($payload['image_base64'])) {
        echo "   Image encoded as base64: " . strlen($payload['image_base64']) . " bytes\n";
    } else if (isset($payload['image_url'])) {
        echo "   Image URL: " . substr($payload['image_url'], 0, 60) . "...\n";
    }
} catch (Exception $e) {
    echo "   ❌ Failed to prepare payload: " . $e->getMessage() . "\n";
}
echo "\n";

// Step 6: Test API generation endpoint
echo "6. Testing API generation...\n";
$context = [
    'filename' => wp_basename($image_url),
    'title' => $image_title,
    'caption' => '',
    'post_title' => '',
];

echo "   Making API request...\n";
$start_time = microtime(true);
$result = $api_client->generate_alt_text($image_id, $context, false);
$duration = microtime(true) - $start_time;

if (is_wp_error($result)) {
    echo "   ❌ Generation failed\n";
    echo "   Error Code: " . $result->get_error_code() . "\n";
    echo "   Error Message: " . $result->get_error_message() . "\n";
    if ($result->get_error_data()) {
        echo "   Error Data: " . print_r($result->get_error_data(), true) . "\n";
    }
} else {
    echo "   ✓ Generation successful\n";
    echo "   Duration: " . number_format($duration, 2) . " seconds\n";
    echo "   Success: " . ($result['success'] ?? 'N/A') . "\n";
    echo "   Alt Text: " . ($result['alt_text'] ?? 'N/A') . "\n";
    if (isset($result['tokens'])) {
        echo "   Tokens Used: " . ($result['tokens']['total_tokens'] ?? 'N/A') . "\n";
    }
}
echo "\n";

// Step 7: Check debug logs
echo "7. Checking recent debug logs...\n";
if (class_exists('BbAI_Debug_Log')) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'beepbeepai_debug_logs';
    $logs = $wpdb->get_results(
        "SELECT * FROM {$table_name} WHERE category IN ('api', 'generation') ORDER BY created_at DESC LIMIT 5",
        ARRAY_A
    );

    if (empty($logs)) {
        echo "   No recent logs found\n";
    } else {
        echo "   Recent logs:\n";
        foreach ($logs as $log) {
            echo "   - [{$log['level']}] {$log['message']} ({$log['created_at']})\n";
            if (!empty($log['context'])) {
                $context_data = json_decode($log['context'], true);
                if ($context_data && isset($context_data['error'])) {
                    echo "     Error: {$context_data['error']}\n";
                }
            }
        }
    }
} else {
    echo "   Debug Log class not available\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Diagnostic complete\n";
