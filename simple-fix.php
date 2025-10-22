<?php
/**
 * SIMPLE FIX: Register new user and save token
 * URL: http://localhost:8080/wp-content/plugins/ai-alt-gpt/simple-fix.php
 */

require_once(__DIR__ . '/../../../wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>AltText AI - Simple Fix</title>
    <style>
        body { font-family: monospace; background: #0d1117; color: #58a6ff; padding: 40px; }
        .box { background: #161b22; border: 1px solid #30363d; padding: 30px; border-radius: 6px; max-width: 800px; margin: 0 auto; }
        .success { color: #3fb950; }
        .error { color: #f85149; }
        .warning { color: #d29922; }
        pre { background: #0d1117; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 12px 24px; background: #238636; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .btn:hover { background: #2ea043; }
    </style>
</head>
<body>
<div class="box">
<h1>🔧 AltText AI - Authentication Fix</h1>
<pre>
<?php

echo "Finding backend...\n";

$backend = 'http://host.docker.internal:3001';
$test = wp_remote_get($backend . '/health', ['timeout' => 5, 'sslverify' => false]);

if (is_wp_error($test) || wp_remote_retrieve_response_code($test) !== 200) {
    echo "<span class='error'>❌ Backend not accessible at $backend</span>\n";
    echo "\nPlease start: cd backend && node server-v2.js\n";
    exit;
}

echo "<span class='success'>✅ Backend found: $backend</span>\n\n";

// Create a unique email each time to avoid conflicts
$email = 'user_' . time() . '@test.com';
$password = 'Password123!';

echo "Creating new user: $email\n";

$response = wp_remote_post($backend . '/auth/register', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode(['email' => $email, 'password' => $password]),
    'timeout' => 30,
    'sslverify' => false
]);

$code = wp_remote_retrieve_response_code($response);
$body = json_decode(wp_remote_retrieve_body($response), true);

if ($code === 200 || $code === 201) {
    // Handle both response structures (direct and wrapped in 'data')
    $responseData = $body['data'] ?? $body;
    $token = $responseData['token'] ?? null;
    $user = $responseData['user'] ?? null;

    if ($token) {
        echo "<span class='success'>✅ User created successfully!</span>\n";
        echo "Token: " . substr($token, 0, 50) . "...\n\n";

        // Save to WordPress
        update_option('alttextai_jwt_token', $token);
        update_option('alttextai_user_data', $user);
        delete_transient('alttextai_token_last_check');

        $opts = get_option('ai_alt_gpt_settings', []);
        $opts['api_url'] = $backend;
        update_option('ai_alt_gpt_settings', $opts);

        echo "<span class='success'>✅ Token saved to WordPress!</span>\n";
        echo "<span class='success'>✅ API URL configured!</span>\n";
        echo "<span class='success'>✅ ALL DONE!</span>\n\n";
        echo "You can now use bulk optimization!\n";
    } else {
        echo "<span class='error'>❌ No token in response</span>\n";
        echo "Response: " . print_r($body, true) . "\n";
    }
} else {
    echo "<span class='error'>❌ Registration failed: HTTP $code</span>\n";
    echo "Response: " . wp_remote_retrieve_body($response) . "\n";
}

?>
</pre>
<a href="/wp-admin/upload.php?page=ai-alt-gpt" class="btn">→ Go to Dashboard</a>
</div>
</body>
</html>
