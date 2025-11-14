#!/usr/bin/env php
<?php
/**
 * Debug what's actually in the payload
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

echo "Debug Image Payload Content\n";
echo str_repeat("=", 60) . "\n\n";

$image_id = 18;
$api_client = new AltText_AI_API_Client_V2();

$image_url = wp_get_attachment_url($image_id);
$title     = get_the_title($image_id);
$caption   = wp_get_attachment_caption($image_id);
$filename  = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

// Get image payload
$reflection = new ReflectionClass($api_client);
$method = $reflection->getMethod('prepare_image_payload');
$method->setAccessible(true);
$image_payload = $method->invoke($api_client, $image_id, $image_url, $title, $caption, $filename);

echo "Payload keys: " . implode(', ', array_keys($image_payload)) . "\n\n";

if (isset($image_payload['image_base64'])) {
    echo "✓ Has image_base64: " . round(strlen($image_payload['image_base64']) / 1024, 2) . " KB\n";
}
if (isset($image_payload['image_url'])) {
    echo "✓ Has image_url: " . $image_payload['image_url'] . "\n";
}
if (isset($image_payload['_error'])) {
    echo "❌ Has error: " . $image_payload['_error'] . "\n";
    echo "   Message: " . $image_payload['_error_message'] . "\n";
}
