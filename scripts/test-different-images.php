#!/usr/bin/env php
<?php
/**
 * Test generation for multiple different images
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

echo "Testing Alt Text Generation for Multiple Images\n";
echo str_repeat("=", 70) . "\n\n";

$api_client = new AltText_AI_API_Client_V2();

// Test images 16-20
$image_ids = [16, 17, 18, 19, 20];

foreach ($image_ids as $image_id) {
    $file_path = get_attached_file($image_id);
    $title = get_the_title($image_id);

    echo "Image #$image_id: $title\n";
    echo "  File: " . basename($file_path) . "\n";

    // Get image URL and check first few bytes to ensure they're different
    if (file_exists($file_path)) {
        $first_bytes = bin2hex(substr(file_get_contents($file_path), 0, 16));
        echo "  First bytes: $first_bytes\n";
    }

    // Generate alt text
    $context = [
        'filename' => basename($file_path),
        'title' => $title,
        'caption' => '',
        'post_title' => '',
    ];

    $result = $api_client->generate_alt_text($image_id, $context, false);

    if (is_wp_error($result)) {
        echo "  ❌ Error: " . $result->get_error_message() . "\n";
    } else {
        $alt_text = $result['alt_text'] ?? 'N/A';
        echo "  ✓ Alt text: " . substr($alt_text, 0, 60) . "...\n";
    }

    echo "\n";
    sleep(1); // Rate limit
}
