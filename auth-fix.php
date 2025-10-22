<?php
/**
 * AltText AI - Authentication Fix
 * Access via: http://localhost:8080/wp-content/plugins/ai-alt-gpt/auth-fix.php
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
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
    die('ERROR: Could not load WordPress');
}

// Start HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>AltText AI - Auth Fix</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            background: #0d1117;
            color: #c9d1d9;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #161b22;
            border-radius: 6px;
            border: 1px solid #30363d;
            padding: 24px;
        }
        h1 { color: #58a6ff; margin-bottom: 20px; font-size: 24px; }
        .output {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
            font-size: 14px;
            max-height: 600px;
            overflow-y: auto;
        }
        .success { color: #3fb950; }
        .error { color: #f85149; }
        .warning { color: #d29922; }
        .info { color: #58a6ff; }
        .step { margin: 12px 0; }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #238636;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 20px;
        }
        .button:hover { background: #2ea043; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 AltText AI - Authentication Fix</h1>
        <div class="output">
<?php

function log_msg($msg, $type = 'info') {
    echo "<div class='step $type'>$msg</div>";
    flush();
    if (ob_get_level() > 0) ob_flush();
}

// Test backends
log_msg("===========================================", 'info');
log_msg("STEP 1: Finding Backend Server", 'info');
log_msg("===========================================", 'info');

$backends = [
    'http://host.docker.internal:3001',
    'http://localhost:3001',
    'https://alttext-ai-backend.onrender.com'
];

$working = null;
foreach ($backends as $url) {
    log_msg("Testing: $url", 'info');
    $r = wp_remote_get($url . '/health', ['timeout' => 5, 'sslverify' => false]);
    if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) {
        log_msg("✅ FOUND: $url", 'success');
        $working = $url;
        break;
    } else {
        log_msg("❌ Not responding", 'error');
    }
}

if (!$working) {
    log_msg("", 'info');
    log_msg("❌ ERROR: No backend found!", 'error');
    log_msg("Start backend: cd backend && node server-v2.js", 'warning');
    echo "</div></div></body></html>";
    exit;
}

log_msg("", 'info');
log_msg("===========================================", 'info');
log_msg("STEP 2: Creating User", 'info');
log_msg("===========================================", 'info');

$email = 'test@test.com';
$pass = 'testtest123';

log_msg("Email: $email", 'info');

$r = wp_remote_post($working . '/auth/register', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode(['email' => $email, 'password' => $pass]),
    'timeout' => 30,
    'sslverify' => false
]);

$code = wp_remote_retrieve_response_code($r);
$body = json_decode(wp_remote_retrieve_body($r), true);
$token = null;

if ($code === 200 || $code === 201) {
    log_msg("✅ User registered!", 'success');
    $token = $body['token'] ?? null;
} elseif ($code === 409) {
    log_msg("⚠️  User exists, logging in...", 'warning');

    $r = wp_remote_post($working . '/auth/login', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode(['email' => $email, 'password' => $pass]),
        'timeout' => 30,
        'sslverify' => false
    ]);

    $code = wp_remote_retrieve_response_code($r);
    $body = json_decode(wp_remote_retrieve_body($r), true);

    if ($code === 200) {
        log_msg("✅ Login successful!", 'success');
        $token = $body['token'] ?? null;
    } else {
        log_msg("❌ Login failed: HTTP $code", 'error');
    }
} else {
    log_msg("❌ Registration failed: HTTP $code", 'error');
}

if (!$token) {
    log_msg("", 'info');
    log_msg("❌ ERROR: No token received", 'error');
    echo "</div></div></body></html>";
    exit;
}

log_msg("Token: " . substr($token, 0, 40) . "...", 'success');

log_msg("", 'info');
log_msg("===========================================", 'info');
log_msg("STEP 3: Saving to WordPress", 'info');
log_msg("===========================================", 'info');

update_option('alttextai_jwt_token', $token);
log_msg("✅ Token saved", 'success');

update_option('alttextai_user_data', $body['user'] ?? []);
log_msg("✅ User data saved", 'success');

delete_transient('alttextai_token_last_check');
log_msg("✅ Cache cleared", 'success');

$opts = get_option('ai_alt_gpt_settings', []);
$opts['api_url'] = $working;
update_option('ai_alt_gpt_settings', $opts);
log_msg("✅ API URL: $working", 'success');

log_msg("", 'info');
log_msg("===========================================", 'info');
log_msg("✅ SETUP COMPLETE!", 'success');
log_msg("===========================================", 'info');
log_msg("", 'info');
log_msg("Now go to your dashboard and try bulk optimization!", 'info');

?>
        </div>
        <a href="/wp-admin/upload.php?page=ai-alt-gpt" class="button">→ Go to Dashboard</a>
    </div>
</body>
</html>
