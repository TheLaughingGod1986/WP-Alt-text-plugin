<?php
/**
 * Force logout - clears ALL authentication credentials
 *
 * Run this from WordPress root:
 * wp eval-file wp-content/plugins/beepbeep-ai-alt-text-generator/scripts/force-logout.php
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

echo "\n=== BeepBeep AI Force Logout Script ===\n\n";

// All option keys to clear
$options_to_clear = array(
    // New unified keys
    'optti_jwt_token',
    'optti_user_data',
    'optti_license_key',
    'optti_license_data',
    'optti_site_id',
    'optti_license_snapshot',
    'optti_license_last_check',
    
    // Legacy beepbeepai keys
    'beepbeepai_jwt_token',
    'beepbeepai_user_data',
    'beepbeepai_license_key',
    'beepbeepai_license_data',
    'beepbeepai_site_id',
    
    // Legacy bbai keys
    'bbai_jwt_token',
    'bbai_user_data',
    'bbai_license_key',
    'bbai_license_data',
    'bbai_site_id',
);

echo "Clearing all authentication options...\n\n";

$cleared_count = 0;
foreach ($options_to_clear as $option_key) {
    $value = get_option($option_key, null);
    if ($value !== null && $value !== '' && $value !== false) {
        delete_option($option_key);
        echo "  ✅ Cleared: {$option_key}\n";
        $cleared_count++;
    } else {
        echo "  ⏭️  Already empty: {$option_key}\n";
    }
}

// Also clear transients
$transients_to_clear = array(
    'bbai_token_last_check',
    'bbai_usage_cache',
    'beepbeepai_usage_cache',
    'beepbeepai_token_last_check',
    'optti_user_info',
    'optti_subscription_info',
    'optti_plans',
);

echo "\nClearing transients...\n\n";
foreach ($transients_to_clear as $transient) {
    delete_transient($transient);
    echo "  ✅ Cleared transient: {$transient}\n";
}

echo "\n=== Summary ===\n";
echo "Cleared {$cleared_count} option(s)\n";
echo "Status: ✅ LOGGED OUT - All credentials have been cleared\n";
echo "\nYou can now log in again from the plugin settings page.\n\n";

