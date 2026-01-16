<?php
/**
 * Agency Overview tab content partial.
 * Displays agency-wide statistics and site breakdown.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get license sites data
$license_sites_data = $this->api_client->get_license_sites();
$sites = [];
$total_alt_text = 0;
$total_images_optimized = 0;
$total_hours_saved = 0;
$this_month_alt_text = 0;

if (!is_wp_error($license_sites_data) && isset($license_sites_data['sites'])) {
    $sites = $license_sites_data['sites'];
    
    // Calculate totals
    foreach ($sites as $site) {
        $alt_text_count = intval($site['altTextCount'] ?? 0);
        $images_optimized = intval($site['imagesOptimized'] ?? 0);
        $total_alt_text += $alt_text_count;
        $total_images_optimized += $images_optimized;
        
        // Calculate hours saved (2.5 minutes per alt text)
        $hours_saved = round(($alt_text_count * 2.5) / 60, 1);
        $total_hours_saved += $hours_saved;
        
        // This month calculation (would need API to provide monthly breakdown)
        // For now, using a simple estimate
        $this_month_alt_text += $alt_text_count; // Placeholder - would need monthly data from API
    }
}

$site_count = count($sites);
$minutes_per_alt_text = 2.5;
$total_hours_saved = round(($total_alt_text * $minutes_per_alt_text) / 60, 1);

// Export URL
$export_url = wp_nonce_url(admin_url('admin-post.php?action=bbai_usage_export'), 'bbai_usage_export');
?>

<!-- Tab Content: Agency Overview -->
<div class="bbai-tab-content active" id="tab-agency-overview">
    <div class="bbai-agency-overview-container">
        <!-- Header Section -->
        <div class="bbai-agency-overview-header">
            <div class="bbai-agency-overview-header-left">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-agency-overview-icon">
                    <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 2V8H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h1 class="bbai-agency-overview-title"><?php esc_html_e('Agency Overview', 'beepbeep-ai-alt-text-generator'); ?></h1>
            </div>
            <div class="bbai-agency-overview-header-right">
                <a href="<?php echo esc_url($export_url); ?>" class="bbai-agency-export-btn">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-export-icon">
                        <path d="M17.5 12.5V16.25C17.5 16.913 17.2366 17.5489 16.7678 18.0178C16.2989 18.4866 15.663 18.75 15 18.75H5C4.33696 18.75 3.70107 18.4866 3.23223 18.0178C2.76339 17.5489 2.5 16.913 2.5 16.25V12.5M14.1667 8.33333L10 12.5M10 12.5L5.83333 8.33333M10 12.5V2.5" stroke="currentColor" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php esc_html_e('Export Report', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="bbai-agency-metrics-grid">
            <div class="bbai-agency-metric-card">
                <div class="bbai-agency-metric-value"><?php echo esc_html(number_format_i18n($total_alt_text)); ?></div>
                <div class="bbai-agency-metric-label"><?php esc_html_e('AI alt Text', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-agency-metric-sublabel"><?php printf(esc_html__('Across %d Site%s', 'beepbeep-ai-alt-text-generator'), $site_count, $site_count !== 1 ? 's' : ''); ?></div>
            </div>
            <div class="bbai-agency-metric-card">
                <div class="bbai-agency-metric-value"><?php echo esc_html(number_format_i18n($this_month_alt_text)); ?></div>
                <div class="bbai-agency-metric-label"><?php esc_html_e('This Month', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
            <div class="bbai-agency-metric-card">
                <div class="bbai-agency-metric-value"><?php echo esc_html(number_format_i18n($total_hours_saved)); ?></div>
                <div class="bbai-agency-metric-label"><?php esc_html_e('Hours Saved', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
        </div>

        <!-- Sites Table -->
        <div class="bbai-agency-sites-table-container">
            <table class="bbai-agency-sites-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Site', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('AI Alt Text Generated', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Images Optimised', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th class="bbai-sortable">
                            <?php esc_html_e('Last Activity', 'beepbeep-ai-alt-text-generator'); ?>
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-sort-icon">
                                <path d="M3 4.5L6 1.5L9 4.5M3 7.5L6 10.5L9 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sites)) : ?>
                        <tr>
                            <td colspan="4" class="bbai-agency-empty-state">
                                <?php esc_html_e('No sites found.', 'beepbeep-ai-alt-text-generator'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($sites as $index => $site) : 
                            $site_name = esc_html($site['siteName'] ?? sprintf(__('Site %d', 'beepbeep-ai-alt-text-generator'), $index + 1));
                            $alt_text_count = intval($site['altTextCount'] ?? 0);
                            $images_optimized = intval($site['imagesOptimized'] ?? 0);
                            $last_activity = $site['lastActivity'] ?? '';
                            
                            // Format last activity date
                            $last_activity_formatted = '';
                            if (!empty($last_activity)) {
                                $timestamp = strtotime($last_activity);
                                if ($timestamp !== false) {
                                    $last_activity_formatted = date_i18n('j M Y', $timestamp);
                                }
                            }
                            if (empty($last_activity_formatted)) {
                                $last_activity_formatted = __('Never', 'beepbeep-ai-alt-text-generator');
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="bbai-site-cell">
                                        <div class="bbai-site-number"><?php echo esc_html($index + 1); ?></div>
                                        <span class="bbai-site-name"><?php echo esc_html( $site_name ); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="bbai-alt-text-cell">
                                        <?php echo esc_html(number_format_i18n($alt_text_count)); ?>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-eye-icon">
                                            <path d="M8 3C4.66667 3 2.07333 5.07333 1 8C2.07333 10.9267 4.66667 13 8 13C11.3333 13 13.9267 10.9267 15 8C13.9267 5.07333 11.3333 3 8 3Z" stroke="currentColor" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8 10C9.10457 10 10 9.10457 10 8C10 6.89543 9.10457 6 8 6C6.89543 6 6 6.89543 6 8C6 9.10457 6.89543 10 8 10Z" stroke="currentColor" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </td>
                                <td><?php echo esc_html(number_format_i18n($images_optimized)); ?></td>
                                <td><?php echo esc_html($last_activity_formatted); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Set plan variables for bottom upsell CTA (only show for non-agency users)
$plan_slug = isset($usage_stats['plan']) ? strtolower($usage_stats['plan']) : 'agency';
$is_free = false;
$is_growth = ($plan_slug === 'pro' || $plan_slug === 'growth');
$is_agency = ($plan_slug === 'agency');

// Only show upsell if not agency (though this tab is agency-only, keeping for consistency)
if (!$is_agency) {
    $bottom_upsell_partial = dirname(__FILE__) . '/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
}
?>
