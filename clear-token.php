<?php
/**
 * Clear AltText AI Authentication Token
 *
 * This script clears the JWT token and user data from WordPress.
 * Visit this file in your browser to clear your authentication and start fresh.
 */

// Load WordPress
require_once __DIR__ . '/local-test/wordpress/wp-load.php';

// Clear all AltText AI authentication data
delete_option('alttextai_jwt_token');
delete_option('alttextai_user_data');
delete_transient('alttextai_token_last_check');

// Also clear the API URL setting to force production
$options = get_option('ai_alt_gpt_settings', []);
$options['api_url'] = 'https://alttext-ai-backend.onrender.com';
update_option('ai_alt_gpt_settings', $options);

?>
<!DOCTYPE html>
<html>
<head>
    <title>AltText AI - Token Cleared</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            text-align: center;
        }
        h1 { color: #667eea; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; margin-bottom: 15px; }
        .success { color: #10b981; font-weight: bold; font-size: 18px; }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .button:hover {
            background: #5568d3;
        }
        ul {
            text-align: left;
            margin: 20px 0;
            padding-left: 20px;
        }
        li { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Authentication Token Cleared</h1>
        <p class="success">Your AltText AI authentication has been reset!</p>

        <p>The following data has been cleared:</p>
        <ul>
            <li>JWT authentication token</li>
            <li>Cached user data</li>
            <li>Token validation cache</li>
            <li>API URL reset to production</li>
        </ul>

        <p><strong>Next Steps:</strong></p>
        <ol style="text-align: left;">
            <li>Go back to your WordPress dashboard</li>
            <li>Refresh the page (Ctrl+R / Cmd+R)</li>
            <li>Click "Sign Up / Login" when you see the purple banner</li>
            <li>Register or login with your account</li>
            <li>Try bulk optimization again!</li>
        </ol>

        <a href="/wp-admin/upload.php?page=ai-alt-gpt" class="button">
            Return to Dashboard
        </a>
    </div>
</body>
</html>
