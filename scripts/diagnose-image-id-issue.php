#!/usr/bin/env php
<?php
/**
 * Diagnostic script to check if image_id is being sent correctly
 * 
 * This will help determine if the issue is in the plugin or backend
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

echo "========================================\n";
echo "Image ID Diagnostic Tool\n";
echo "========================================\n\n";

// Step 1: Check if we can get different attachment IDs
echo "1. Finding test images...\n";
$args = [
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'posts_per_page' => 5,
    'post_status' => 'inherit',
    'orderby' => 'ID',
    'order' => 'DESC',
];
$images = get_posts($args);

if (empty($images)) {
    echo "   ❌ No images found in media library\n";
    exit(1);
}

echo "   ✓ Found " . count($images) . " images\n\n";

// Step 2: Test each image ID
echo "2. Testing image ID extraction for each image...\n\n";

$api_client = new BbAI_API_Client_V2();
$reflection = new ReflectionClass($api_client);
$method = $reflection->getMethod('prepare_image_payload');
$method->setAccessible(true);

foreach ($images as $image) {
    $image_id = $image->ID;
    $image_url = wp_get_attachment_url($image_id);
    $title = get_the_title($image_id);
    $caption = get_post_meta($image_id, '_wp_attachment_image_alt', true);
    $filename = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';
    
    echo "   Image ID: {$image_id}\n";
    echo "   Title: {$title}\n";
    echo "   URL: " . substr($image_url, 0, 60) . "...\n";
    
    // Test prepare_image_payload
    $payload = $method->invoke($api_client, $image_id, $image_url, $title, $caption, $filename);
    
    echo "   Payload image_id: " . ($payload['image_id'] ?? 'MISSING') . "\n";
    
    if (!isset($payload['image_id']) || (string)$payload['image_id'] !== (string)$image_id) {
        echo "   ⚠️  WARNING: image_id mismatch!\n";
        echo "      Expected: {$image_id}\n";
        echo "      Got: " . ($payload['image_id'] ?? 'MISSING') . "\n";
    } else {
        echo "   ✓ image_id matches\n";
    }
    
    echo "   Has image_url: " . (!empty($payload['image_url']) ? 'YES' : 'NO') . "\n";
    if (!empty($payload['image_url'])) {
        echo "   image_url: " . substr($payload['image_url'], 0, 80) . "...\n";
    }
    echo "   Has image_base64: " . (!empty($payload['image_base64']) ? 'YES (' . strlen($payload['image_base64']) . ' chars)' : 'NO') . "\n";
    echo "\n";
}

// Step 3: Simulate what would be sent to backend
echo "3. Simulating request payload (what backend should receive)...\n\n";

$test_image_id = $images[0]->ID;
$image_url = wp_get_attachment_url($test_image_id);
$title = get_the_title($test_image_id);
$filename = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

$image_payload = $method->invoke($api_client, $test_image_id, $image_url, $title, '', $filename);

$body = [
    'image_data' => $image_payload,
    'context' => [
        'filename' => $filename,
        'title' => $title,
    ],
    'regenerate' => true,
    'timestamp' => time(),
    'image_id' => (string) $test_image_id,
];

echo "   Root level image_id: " . $body['image_id'] . "\n";
echo "   image_data.image_id: " . ($body['image_data']['image_id'] ?? 'MISSING') . "\n";

if ((string)$body['image_id'] !== (string)$body['image_data']['image_id']) {
    echo "   ⚠️  WARNING: Root image_id and image_data.image_id don't match!\n";
} else {
    echo "   ✓ Both image_id values match\n";
}

echo "\n4. JSON payload preview (first 500 chars):\n";
$json = json_encode($body, JSON_PRETTY_PRINT);
echo substr($json, 0, 500) . "...\n\n";

echo "========================================\n";
echo "Diagnostic Complete\n";
echo "========================================\n\n";

echo "Next steps:\n";
echo "1. Check browser console when clicking regenerate buttons\n";
echo "2. Check WordPress debug logs for 'Regenerate request received'\n";
echo "3. Check backend logs to see what image_id it's receiving\n";
echo "4. If plugin logs show correct IDs but backend shows wrong ID, it's a backend issue\n";
echo "5. If plugin logs show wrong IDs, it's a plugin issue\n";

