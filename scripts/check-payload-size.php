#!/usr/bin/env php
<?php
/**
 * Check Actual Payload Size
 * Shows the actual HTTP payload size being sent
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

echo "Payload Size Check\n";
echo str_repeat("=", 60) . "\n\n";

// Test with image #20
$image_id = 20;
$api_client = new BbAI_API_Client_V2();

$image_url = wp_get_attachment_url($image_id);
$title     = get_the_title($image_id);
$caption   = wp_get_attachment_caption($image_id);
$filename  = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

// Get image payload
$reflection = new ReflectionClass($api_client);
$method = $reflection->getMethod('prepare_image_payload');
$method->setAccessible(true);
$image_payload = $method->invoke($api_client, $image_id, $image_url, $title, $caption, $filename);

$context = [
    'filename' => $filename,
    'title' => $title,
    'caption' => $caption,
    'post_title' => '',
];

$body = [
    'image_data' => $image_payload,
    'context' => $context,
    'regenerate' => false
];

$json_payload = wp_json_encode($body);
$payload_size = strlen($json_payload);

echo "Image #$image_id Payload Analysis:\n";
echo "  Image base64 size: " . (isset($image_payload['image_base64']) ? strlen($image_payload['image_base64']) : 0) . " bytes\n";
echo "  Context size: " . strlen(wp_json_encode($context)) . " bytes\n";
echo "  Total JSON payload: " . $payload_size . " bytes (" . round($payload_size / 1024, 2) . " KB)\n";
echo "\n";

if ($payload_size > 100 * 1024) {
    echo "⚠️  Payload is over 100KB - might trigger backend limit\n";
}
if ($payload_size > 500 * 1024) {
    echo "⚠️  Payload is over 500KB - will likely trigger backend limit\n";
}
