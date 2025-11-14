<?php
/**
 * Reset User Usage Count
 * Resets the monthly generation count for a user in the backend database
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

$user_email = isset($argv[1]) ? $argv[1] : null;

// Try to get from stored user data
if (!$user_email) {
    $user_data = get_option('alttextai_user_data', null);
    if (is_array($user_data) && isset($user_data['email'])) {
        $user_email = $user_data['email'];
    }
}

if (!$user_email) {
    die("Error: User email required. Usage: php reset-usage.php [email]\n");
}

echo "🔄 Resetting Usage Count for: {$user_email}\n";
echo str_repeat("=", 60) . "\n\n";

// Method 1: Reset via direct database access (if configured)
if (defined('ALTTEXT_AI_DB_ENABLED') && ALTTEXT_AI_DB_ENABLED && class_exists('AltText_AI_Direct_DB_Usage')) {
    echo "1. Attempting direct database reset...\n";
    
    $db_host = defined('ALTTEXT_AI_DB_HOST') ? ALTTEXT_AI_DB_HOST : null;
    $db_name = defined('ALTTEXT_AI_DB_NAME') ? ALTTEXT_AI_DB_NAME : null;
    $db_user = defined('ALTTEXT_AI_DB_USER') ? ALTTEXT_AI_DB_USER : null;
    $db_pass = defined('ALTTEXT_AI_DB_PASSWORD') ? ALTTEXT_AI_DB_PASSWORD : null;
    $db_type = defined('ALTTEXT_AI_DB_TYPE') ? ALTTEXT_AI_DB_TYPE : 'mysql';
    $usage_table = defined('ALTTEXT_AI_DB_USAGE_TABLE') ? ALTTEXT_AI_DB_USAGE_TABLE : 'usage';
    $user_table = defined('ALTTEXT_AI_DB_USER_TABLE') ? ALTTEXT_AI_DB_USER_TABLE : 'users';
    
    if ($db_host && $db_name && $db_user && $db_pass) {
        try {
            if ($db_type === 'mysql' || $db_type === 'mariadb') {
                $host_parts = explode(':', $db_host);
                $db_hostname = $host_parts[0];
                $db_port = isset($host_parts[1]) ? intval($host_parts[1]) : 3306;
                
                $mysqli = new mysqli($db_hostname, $db_user, $db_pass, $db_name, $db_port);
                
                if ($mysqli->connect_error) {
                    echo "   ✗ Database connection failed: " . $mysqli->connect_error . "\n";
                } else {
                    // Get user ID first
                    $email_escaped = $mysqli->real_escape_string($user_email);
                    $user_query = "SELECT id FROM {$user_table} WHERE email = '{$email_escaped}' LIMIT 1";
                    $user_result = $mysqli->query($user_query);
                    
                    if ($user_result && $user_result->num_rows > 0) {
                        $user_row = $user_result->fetch_assoc();
                        $user_id = intval($user_row['id']);
                        
                        // Delete usage records for current month
                        $current_month_start = date('Y-m-01');
                        $delete_query = "
                            DELETE FROM {$usage_table} 
                            WHERE user_id = {$user_id} 
                            AND created_at >= '{$current_month_start}'
                        ";
                        
                        if ($mysqli->query($delete_query)) {
                            $deleted = $mysqli->affected_rows;
                            echo "   ✓ Deleted {$deleted} usage records for current month\n";
                            echo "   ✓ Usage count reset to 0\n";
                        } else {
                            echo "   ✗ Failed to delete usage records: " . $mysqli->error . "\n";
                        }
                    } else {
                        echo "   ✗ User not found in database\n";
                    }
                    
                    $mysqli->close();
                }
            } elseif ($db_type === 'postgresql' || $db_type === 'postgres') {
                $host_parts = explode(':', $db_host);
                $db_hostname = $host_parts[0];
                $db_port = isset($host_parts[1]) ? intval($host_parts[1]) : 5432;
                
                $connection_string = sprintf(
                    "host=%s port=%d dbname=%s user=%s password=%s",
                    $db_hostname,
                    $db_port,
                    $db_name,
                    $db_user,
                    $db_pass
                );
                
                $pg_conn = pg_connect($connection_string);
                
                if (!$pg_conn) {
                    echo "   ✗ PostgreSQL connection failed\n";
                } else {
                    $email_escaped = pg_escape_string($user_email);
                    $user_query = "SELECT id FROM {$user_table} WHERE email = '{$email_escaped}' LIMIT 1";
                    $user_result = pg_query($pg_conn, $user_query);
                    
                    if ($user_result && pg_num_rows($user_result) > 0) {
                        $user_row = pg_fetch_assoc($user_result);
                        $user_id = intval($user_row['id']);
                        
                        $current_month_start = date('Y-m-01');
                        $delete_query = "
                            DELETE FROM {$usage_table} 
                            WHERE user_id = {$user_id} 
                            AND created_at >= '{$current_month_start}'
                        ";
                        
                        $delete_result = pg_query($pg_conn, $delete_query);
                        if ($delete_result) {
                            $deleted = pg_affected_rows($delete_result);
                            echo "   ✓ Deleted {$deleted} usage records for current month\n";
                            echo "   ✓ Usage count reset to 0\n";
                        } else {
                            echo "   ✗ Failed to delete usage records: " . pg_last_error($pg_conn) . "\n";
                        }
                    } else {
                        echo "   ✗ User not found in database\n";
                    }
                    
                    pg_close($pg_conn);
                }
            }
        } catch (\Exception $e) {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ⚠️  Database credentials not fully configured\n";
    }
    echo "\n";
}

// Method 2: Clear WordPress cache
echo "2. Clearing WordPress usage cache...\n";
AltText_AI_Usage_Tracker::clear_cache();
echo "   ✓ Cache cleared\n\n";

// Method 3: Refresh from API to get updated count
echo "3. Refreshing usage from API...\n";
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
$api_client = new AltText_AI_API_Client_V2();

if ($api_client->is_authenticated()) {
    $usage = $api_client->get_usage();
    if (is_array($usage) && !empty($usage)) {
        AltText_AI_Usage_Tracker::update_usage($usage);
        echo "   ✓ Refreshed from API:\n";
        echo "     Used: " . ($usage['used'] ?? 0) . "\n";
        echo "     Limit: " . ($usage['limit'] ?? 50) . "\n";
        echo "     Remaining: " . ($usage['remaining'] ?? 50) . "\n";
        
        if (($usage['used'] ?? 0) > 0) {
            echo "   ⚠️  Usage still shows " . ($usage['used'] ?? 0) . " - backend may need time to update\n";
            echo "      Or direct database reset wasn't successful\n";
        } else {
            echo "   ✓ Usage successfully reset to 0!\n";
        }
    } else {
        echo "   ⚠️  Could not refresh from API\n";
    }
} else {
    echo "   ⚠️  Not authenticated - cannot refresh from API\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Reset Complete!\n";
echo "\n";
echo "Note: If direct database access is not configured, you'll need to:\n";
echo "1. Access the backend database (alttext-ai-db) directly\n";
echo "2. Run: DELETE FROM usage WHERE user_id = [user_id] AND created_at >= DATE_TRUNC('month', CURRENT_DATE);\n";
echo "3. Or contact backend support to reset usage\n";
echo "\n";
