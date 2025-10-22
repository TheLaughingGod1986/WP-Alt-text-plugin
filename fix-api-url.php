<?php
/**
 * Fix API URL - Run this once from WordPress admin
 * Visit: /wp-admin/admin.php?page=ai-alt-gpt&fix_api_url=1
 */

// This will be triggered from the settings page
add_action('admin_init', function() {
    if (isset($_GET['fix_api_url']) && $_GET['fix_api_url'] == '1' && current_user_can('manage_options')) {
        $options = get_option('ai_alt_gpt_settings', []);
        $options['api_url'] = 'https://alttext-ai-backend.onrender.com';
        update_option('ai_alt_gpt_settings', $options);

        // Clear any cached token (might be invalid)
        delete_option('alttextai_jwt_token');
        delete_option('alttextai_user_data');
        delete_transient('alttextai_token_last_check');

        echo '<div class="notice notice-success"><p>✅ API URL fixed! Set to: https://alttext-ai-backend.onrender.com</p></div>';
        echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('upload.php?page=ai-alt-gpt') . '"; }, 2000);</script>';
        exit;
    }
});
