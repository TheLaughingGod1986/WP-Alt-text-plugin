<?php
/**
 * Test OpenAI API Key Validity
 */

// Get API key from command line argument or environment variable
$api_key = $argv[1] ?? $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? '';
if (empty($api_key)) {
    echo "Usage: php test-openai-key.php <api_key>\n";
    echo "Or set OPENAI_API_KEY environment variable\n";
    exit(1);
}

echo "Testing OpenAI API Key\n";
echo str_repeat("=", 50) . "\n\n";

// Test with a simple API call
$url = 'https://api.openai.com/v1/models';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if (!empty($curl_error)) {
    echo "❌ cURL Error: " . $curl_error . "\n";
    exit(1);
}

echo "HTTP Status Code: " . $http_code . "\n\n";

if ($http_code === 200) {
    $data = json_decode($response, true);
    if (isset($data['data']) && is_array($data['data'])) {
        echo "✅ API Key is VALID\n\n";
        echo "Available Models: " . count($data['data']) . " models found\n";
        echo "First few models:\n";
        foreach (array_slice($data['data'], 0, 5) as $model) {
            echo "  - " . ($model['id'] ?? 'N/A') . "\n";
        }
    } else {
        echo "⚠️  Unexpected response format\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
    }
} elseif ($http_code === 401) {
    echo "❌ API Key is INVALID (401 Unauthorized)\n";
    echo "   The key may be expired, revoked, or incorrect.\n";
} elseif ($http_code === 429) {
    echo "⚠️  Rate limit exceeded (429)\n";
    echo "   The key is valid but you've hit rate limits.\n";
} else {
    echo "❌ Error: HTTP $http_code\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

