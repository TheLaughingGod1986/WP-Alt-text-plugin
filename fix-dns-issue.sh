#!/bin/bash

# Fix DNS and Queue Issues Script
# This script fixes intermittent DNS failures in Docker WordPress

set -e

echo "🔧 AltText AI - Fixing DNS and Queue Issues"
echo "============================================="
echo ""

# Step 1: Restart containers with new DNS config
echo "📦 Step 1: Restarting containers with stable DNS..."
docker-compose down
docker-compose up -d
echo "✅ Containers restarted with Google DNS (8.8.8.8)"
echo ""

# Step 2: Wait for WordPress to be ready
echo "⏳ Waiting for WordPress to start..."
sleep 10
echo "✅ WordPress should be ready"
echo ""

# Step 3: Clear API error log
echo "🧹 Step 2: Clearing API error log..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
delete_option('alttextai_api_error_log');
echo '✅ Error log cleared!' . PHP_EOL;
"
echo ""

# Step 4: Clear transients
echo "🧹 Step 3: Clearing cached data..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';
delete_transient('alttextai_token_last_check');
delete_transient('alttextai_backend_status');
wp_cache_flush();
echo '✅ Cache cleared!' . PHP_EOL;
"
echo ""

# Step 5: Reset stuck queue items
echo "🔄 Step 4: Resetting stuck queue items..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';

global \$wpdb;
\$table = \$wpdb->prefix . 'ai_alt_gpt_queue';

// Count stuck items
\$stuck = \$wpdb->get_var(
    \"SELECT COUNT(*) FROM \$table WHERE attempts >= 3 AND status = 'pending'\"
);

if (\$stuck > 0) {
    // Reset them
    \$updated = \$wpdb->query(
        \"UPDATE \$table SET attempts = 0, last_error = '' WHERE attempts >= 3 AND status = 'pending'\"
    );
    echo \"✅ Reset \$updated stuck queue items!\" . PHP_EOL;
} else {
    echo '✅ No stuck items found!' . PHP_EOL;
}
"
echo ""

# Step 6: Test backend connectivity
echo "🔍 Step 5: Testing backend connectivity..."
docker exec wp-alt-text-plugin-wordpress-1 curl -s -w "\nHTTP Status: %{http_code}\n" https://alttext-ai-backend.onrender.com/health
echo ""

# Step 7: Test WordPress HTTP API
echo "🔍 Step 6: Testing WordPress HTTP API..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';

\$response = wp_remote_get('https://alttext-ai-backend.onrender.com/health', ['timeout' => 15]);

if (is_wp_error(\$response)) {
    echo '❌ FAILED: ' . \$response->get_error_message() . PHP_EOL;
    exit(1);
} else {
    \$code = wp_remote_retrieve_response_code(\$response);
    if (\$code === 200) {
        echo '✅ WordPress HTTP API can reach backend successfully!' . PHP_EOL;
    } else {
        echo '⚠️  Unexpected status code: ' . \$code . PHP_EOL;
    }
}
"
echo ""

# Step 8: Check queue status
echo "📊 Step 7: Checking queue status..."
docker exec wp-alt-text-plugin-wordpress-1 php -r "
define('ABSPATH', '/var/www/html/');
\$_SERVER['HTTP_HOST'] = 'localhost';
require '/var/www/html/wp-load.php';

global \$wpdb;
\$table = \$wpdb->prefix . 'ai_alt_gpt_queue';

\$pending = \$wpdb->get_var(\"SELECT COUNT(*) FROM \$table WHERE status = 'pending'\");
\$processing = \$wpdb->get_var(\"SELECT COUNT(*) FROM \$table WHERE status = 'processing'\");
\$failed = \$wpdb->get_var(\"SELECT COUNT(*) FROM \$table WHERE status = 'failed'\");
\$completed = \$wpdb->get_var(\"SELECT COUNT(*) FROM \$table WHERE status = 'completed'\");

echo \"Queue Status:\" . PHP_EOL;
echo \"  Pending: \$pending\" . PHP_EOL;
echo \"  Processing: \$processing\" . PHP_EOL;
echo \"  Failed: \$failed\" . PHP_EOL;
echo \"  Completed: \$completed\" . PHP_EOL;
"
echo ""

echo "============================================="
echo "✅ All fixes applied successfully!"
echo ""
echo "Next steps:"
echo "1. Visit http://localhost:8080/wp-admin"
echo "2. Go to Media → AltText AI"
echo "3. The queue should start processing automatically"
echo ""
echo "If queue doesn't start, manually trigger it:"
echo "  docker exec wp-alt-text-plugin-wordpress-1 wp cron event run ai_alt_gpt_process_queue --allow-root"
echo ""
