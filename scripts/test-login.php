<?php
/**
 * Test WordPress Login
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

echo "Testing WordPress Login:\n";
echo str_repeat("=", 50) . "\n\n";

// Test black/black
$test1 = wp_authenticate('black', 'black');
if (is_wp_error($test1)) {
    echo "❌ black/black: " . $test1->get_error_message() . "\n";
} else {
    echo "✓ black/black: SUCCESS (User ID: {$test1->ID})\n";
}

// Test root/black
$test2 = wp_authenticate('root', 'black');
if (is_wp_error($test2)) {
    echo "❌ root/black: " . $test2->get_error_message() . "\n";
} else {
    echo "✓ root/black: SUCCESS (User ID: {$test2->ID})\n";
}

echo "\n";
echo "You can log in with EITHER:\n";
echo "  Username: black, Password: black\n";
echo "  OR\n";
echo "  Username: root, Password: black\n";
echo "\n";
