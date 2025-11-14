#!/usr/bin/env php
<?php
/**
 * Check Image Sizes
 * Shows file sizes of images that are failing
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

echo "Image Size Check\n";
echo str_repeat("=", 60) . "\n\n";

// Get images #16, #18, #19, #20 (the ones failing)
$image_ids = [16, 18, 19, 20];

foreach ($image_ids as $id) {
    $file_path = get_attached_file($id);
    if ($file_path && file_exists($file_path)) {
        $file_size = filesize($file_path);
        $metadata = wp_get_attachment_metadata($id);
        $width = $metadata['width'] ?? 'N/A';
        $height = $metadata['height'] ?? 'N/A';
        $mime_type = get_post_mime_type($id);

        echo "Image #$id:\n";
        echo "  File: " . basename($file_path) . "\n";
        echo "  Size: " . round($file_size / 1024 / 1024, 2) . " MB (" . number_format($file_size) . " bytes)\n";
        echo "  Dimensions: {$width}x{$height}\n";
        echo "  Type: $mime_type\n";
        echo "\n";
    } else {
        echo "Image #$id: File not found\n\n";
    }
}
