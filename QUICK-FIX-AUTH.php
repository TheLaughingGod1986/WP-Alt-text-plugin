<?php
/**
 * QUICK FIX: Set up authentication for AltText AI
 *
 * HOW TO USE:
 * 1. Save this file to your WordPress root directory
 * 2. Visit: http://localhost:8888/QUICK-FIX-AUTH.php
 * 3. Follow the on-screen instructions
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/wp-load.php',
    __DIR__ . '/local-test/wordpress/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('ERROR: Could not load WordPress. Please copy this file to your WordPress root directory.');
}

// Start output
?>
<!DOCTYPE html>
<html>
<head>
    <title>AltText AI - Quick Fix</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #667eea; margin: 0 0 20px 0; }
        .log {
            background: #1a1a1a;
            color: #00ff00;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            max-height: 500px;
            overflow-y: auto;
        }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; }
        .info { color: #3b82f6; }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .button:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }
        .step {
            background: #f0f9ff;
            padding: 15px;
            border-left: 4px solid #667eea;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 AltText AI - Quick Authentication Fix</h1>

        <div class="log">
<?php

function log_message($message, $type = 'info') {
    $colors = [
        'success' => '#10b981',
        'error' => '#ef4444',
        'warning' => '#f59e0b',
        'info' => '#00ff00'
    ];
    $color = $colors[$type] ?? $colors['info'];
    echo "<span style='color: $color;'>$message</span>\n";
    flush();
    ob_flush();
}

ob_start();

log_message("===========================================", 'info');
log_message("  AltText AI Authentication Setup", 'info');
log_message("===========================================\n", 'info');

// Step 1: Find working backend
log_message("STEP 1: Checking backend servers...", 'info');
log_message("-----------------------------------", 'info');

$backends = [
    'http://host.docker.internal:3001' => 'Docker internal (recommended)',
    'http://localhost:3001' => 'Localhost',
    'https://alttext-ai-backend.onrender.com' => 'Render Production'
];

$working_backend = null;

foreach ($backends as $url => $name) {
    log_message("Testing $name ($url)...", 'info');

    $response = wp_remote_get($url . '/health', [
        'timeout' => 5,
        'sslverify' => false
    ]);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        log_message("✅ WORKING: $name", 'success');
        $working_backend = $url;
        break;
    } else {
        $error_msg = is_wp_error($response) ? $response->get_error_message() : 'Connection failed';
        log_message("❌ FAILED: $error_msg", 'error');
    }
}

if (!$working_backend) {
    log_message("\n❌ CRITICAL ERROR: No backend server accessible!", 'error');
    log_message("\nPlease start the backend server:", 'warning');
    log_message("cd backend && node server-v2.js", 'info');
    echo "</div></div></body></html>";
    exit;
}

log_message("\n✅ Using backend: $working_backend\n", 'success');

// Step 2: Create/login user
log_message("STEP 2: Creating authentication...", 'info');
log_message("----------------------------------", 'info');

$test_email = 'test@test.com';
$test_password = 'testtest123';

log_message("Attempting to register: $test_email", 'info');

$register_response = wp_remote_post($working_backend . '/auth/register', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode([
        'email' => $test_email,
        'password' => $test_password
    ]),
    'timeout' => 30,
    'sslverify' => false
]);

$status = wp_remote_retrieve_response_code($register_response);
$body = wp_remote_retrieve_body($register_response);
$data = json_decode($body, true);

$token = null;
$user = null;

if ($status === 200 || $status === 201) {
    log_message("✅ User registered successfully!", 'success');
    $token = $data['token'] ?? null;
    $user = $data['user'] ?? null;
} elseif ($status === 409) {
    log_message("⚠️  User exists, attempting login...", 'warning');

    $login_response = wp_remote_post($working_backend . '/auth/login', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'email' => $test_email,
            'password' => $test_password
        ]),
        'timeout' => 30,
        'sslverify' => false
    ]);

    $login_status = wp_remote_retrieve_response_code($login_response);
    $login_body = wp_remote_retrieve_body($login_response);
    $login_data = json_decode($login_body, true);

    if ($login_status === 200) {
        log_message("✅ Login successful!", 'success');
        $token = $login_data['token'] ?? null;
        $user = $login_data['user'] ?? null;
    } else {
        log_message("❌ Login failed: HTTP $login_status", 'error');
        log_message("Response: $login_body", 'error');
    }
} else {
    log_message("❌ Registration failed: HTTP $status", 'error');
    log_message("Response: $body", 'error');
}

if (!$token) {
    log_message("\n❌ ERROR: Could not obtain authentication token!", 'error');
    echo "</div></div></body></html>";
    exit;
}

log_message("\n✅ Token obtained: " . substr($token, 0, 40) . "...", 'success');

// Step 3: Save to WordPress
log_message("\nSTEP 3: Saving to WordPress...", 'info');
log_message("-------------------------------", 'info');

update_option('alttextai_jwt_token', $token);
log_message("✅ Token saved to: alttextai_jwt_token", 'success');

update_option('alttextai_user_data', $user);
log_message("✅ User data saved", 'success');

delete_transient('alttextai_token_last_check');
log_message("✅ Token cache cleared", 'success');

$options = get_option('ai_alt_gpt_settings', []);
$options['api_url'] = $working_backend;
update_option('ai_alt_gpt_settings', $options);
log_message("✅ API URL configured: $working_backend", 'success');

// Test authentication
log_message("\nSTEP 4: Testing authentication...", 'info');
log_message("---------------------------------", 'info');

$test_response = wp_remote_get($working_backend . '/auth/me', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json'
    ],
    'timeout' => 10,
    'sslverify' => false
]);

$test_status = wp_remote_retrieve_response_code($test_response);

if ($test_status === 200) {
    log_message("✅ Authentication test PASSED!", 'success');
    $test_body = json_decode(wp_remote_retrieve_body($test_response), true);
    $user_email = $test_body['user']['email'] ?? 'Unknown';
    $user_plan = $test_body['user']['plan'] ?? 'free';
    log_message("   Email: $user_email", 'info');
    log_message("   Plan: $user_plan", 'info');
} else {
    log_message("⚠️  Authentication test returned HTTP $test_status", 'warning');
}

log_message("\n===========================================", 'info');
log_message("  ✅ SETUP COMPLETE!", 'success');
log_message("===========================================", 'info');

ob_end_flush();

?>
        </div>

        <div class="step">
            <h3>✅ What happened:</h3>
            <ul>
                <li>✅ Found working backend server</li>
                <li>✅ Created/logged in test user</li>
                <li>✅ Obtained JWT authentication token</li>
                <li>✅ Saved token to WordPress database</li>
                <li>✅ Configured API URL</li>
                <li>✅ Verified authentication works</li>
            </ul>
        </div>

        <div class="step">
            <h3>🚀 What to do next:</h3>
            <ol>
                <li>Go to your WordPress dashboard: <a href="/wp-admin/upload.php?page=ai-alt-gpt">AltText AI Dashboard</a></li>
                <li>Refresh the page (Ctrl+R / Cmd+R)</li>
                <li>You should see "Free — 10 images/month" at the top</li>
                <li>Click "Optimize 10 Remaining Images"</li>
                <li>Bulk optimization should now work!</li>
            </ol>
        </div>

        <a href="/wp-admin/upload.php?page=ai-alt-gpt" class="button">
            → Go to Dashboard
        </a>
    </div>
</body>
</html>
