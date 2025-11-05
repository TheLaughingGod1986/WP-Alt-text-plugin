<?php
/**
 * Test if Backend API is using a valid OpenAI key
 * Makes a real generation request to see if OpenAI is working
 */

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

$base_url = 'https://alttext-ai-backend.onrender.com';
$token = get_option('alttextai_jwt_token', '');

if (empty($token)) {
    echo "❌ No authentication token found\n";
    exit(1);
}

echo "Testing Backend API OpenAI Configuration\n";
echo str_repeat("=", 50) . "\n\n";

// Test with a simple image URL (using a placeholder)
// The backend should attempt to call OpenAI
echo "Making test generation request...\n";
echo "This will test if the backend has a valid OpenAI key configured\n\n";

$ch = curl_init($base_url . '/api/generate');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'image_url' => 'https://via.placeholder.com/300x300.jpg',
    'attachment_id' => 999
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Longer timeout for OpenAI call

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $http_code\n\n";

if ($http_code === 200) {
    $data = json_decode($response, true);
    if (isset($data['success']) && $data['success']) {
        echo "✅ Backend API is working with OpenAI\n";
        echo "   Alt text generated: " . substr($data['alt_text'] ?? 'N/A', 0, 100) . "\n";
        if (isset($data['tokens'])) {
            echo "   Tokens used: " . ($data['tokens']['total_tokens'] ?? 'N/A') . "\n";
        }
    } else {
        echo "⚠️  API responded but generation may have failed\n";
        echo "   Response: " . substr(json_encode($data), 0, 200) . "\n";
    }
} elseif ($http_code === 401) {
    echo "❌ Authentication failed\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
} elseif ($http_code === 500) {
    echo "❌ Server error - backend may have issues\n";
    echo "   Response: " . substr($response, 0, 300) . "\n";
    if (strpos($response, 'OpenAI') !== false || strpos($response, 'API key') !== false) {
        echo "\n   ⚠️  This error may indicate an OpenAI key issue\n";
    }
} else {
    echo "❌ Unexpected response\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

if (!empty($curl_error)) {
    echo "\n   cURL Error: $curl_error\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "\nNote: The OpenAI key is configured in the backend API's environment variables on Render.\n";
echo "To update it, go to Render Dashboard → Your Backend Service → Environment → Add/Update OPENAI_API_KEY\n";

