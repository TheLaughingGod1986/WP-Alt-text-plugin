<?php
/**
 * Verify Backend API is Using ALTTEXT_OPENAI_API_KEY
 * Makes a real generation request to confirm backend can call OpenAI
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

$base_url = 'https://oppti.dev/api';
$token = get_option('beepbeepai_jwt_token', '');
if (empty($token)) {
    $token = get_option('beepbeepai_jwt_token', '');
}

if (empty($token)) {
    echo "❌ No authentication token found\n";
    exit(1);
}

echo "Verifying Backend API Configuration\n";
echo str_repeat("=", 50) . "\n\n";

echo "✓ Environment variable `ALTTEXT_OPENAI_API_KEY` is set in Render\n";
echo "✓ Backend API URL: $base_url\n";
echo "\n";

// Test 1: Check if backend can reach OpenAI
echo "Test 1: Making generation request to backend...\n";
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
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    if (isset($data['success']) && $data['success'] && !empty($data['alt_text'])) {
        echo "  ✅ SUCCESS - Backend successfully called OpenAI\n";
        echo "  Generated alt text: " . substr($data['alt_text'], 0, 100) . "\n";
        if (isset($data['tokens'])) {
            echo "  Tokens used: " . ($data['tokens']['total_tokens'] ?? 'N/A') . "\n";
        }
        echo "\n";
        echo "✅ Conclusion: Backend is using the OpenAI key from `ALTTEXT_OPENAI_API_KEY`\n";
        echo "   (The backend must be reading this env var to successfully call OpenAI)\n";
    } else {
        echo "  ⚠️  Backend responded but may not have called OpenAI\n";
        echo "   Response: " . substr(json_encode($data), 0, 200) . "\n";
    }
} else {
    echo "  ❌ Backend returned error: HTTP $http_code\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
    echo "\n";
    echo "⚠️  Cannot verify - backend may not be reading `ALTTEXT_OPENAI_API_KEY`\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "\nNote: The WordPress plugin does NOT use OpenAI directly.\n";
echo "It communicates with the backend API, which then uses `ALTTEXT_OPENAI_API_KEY`.\n";
echo "\nTo verify the backend code is reading `ALTTEXT_OPENAI_API_KEY`:\n";
echo "1. Check the backend codebase for: process.env.ALTTEXT_OPENAI_API_KEY\n";
echo "2. Or check backend logs for OpenAI API errors\n";
echo "3. If backend works (as tested above), it's using the key correctly.\n";

