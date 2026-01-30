<?php
/**
 * Debug Logs tab content partial.
 *
 * Expects $this (core class), $debug_bootstrap, and $usage_stats in scope.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php
// Check authentication - accept either JWT token OR active license
$is_authenticated = $this->api_client->is_authenticated();
$has_license = $this->api_client->has_active_license();
$can_access_debug = $is_authenticated || $has_license;

if (!$can_access_debug) : ?>
    <!-- Debug Logs require authentication -->
    <div class="bbai-dashboard-container">
        <div class="bbai-settings-required">
            <div class="bbai-settings-required-content">
                <div class="bbai-settings-required-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="4" y="10" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M7 10V7C7 4.23858 9.23858 2 12 2C14.7614 2 17 4.23858 17 7V10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <h2><?php esc_html_e('Authentication Required', 'opptiai-alt'); ?></h2>
                <p><?php esc_html_e('Debug Logs are available to all authenticated users. Please log in or enter your license key to access this section.', 'opptiai-alt'); ?></p>
                <p class="bbai-settings-required-note">
                    <?php esc_html_e('If you don\'t have an account, you can create one for free or subscribe to a plan.', 'opptiai-alt'); ?>
                </p>
                <div class="bbai-settings-required-actions">
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-icon" data-action="show-auth-modal" data-auth-tab="login">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <circle cx="8" cy="8" r="2" fill="currentColor"/>
                        </svg>
                        <span><?php esc_html_e('Log In', 'opptiai-alt'); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) : ?>
    <div class="bbai-section">
        <div class="notice notice-warning">
            <p><?php esc_html_e('Debug logging is not available on this site. Please reinstall the logging module.', 'opptiai-alt'); ?></p>
        </div>
    </div>
<?php else : ?>
    <?php
        $debug_logs       = $debug_bootstrap['logs'] ?? [];
        $debug_stats      = $debug_bootstrap['stats'] ?? [];
        $debug_pagination = $debug_bootstrap['pagination'] ?? [];
        $debug_page       = max(1, intval($debug_pagination['page'] ?? 1));
        $debug_pages      = max(1, intval($debug_pagination['total_pages'] ?? 1));
        $debug_export_url = wp_nonce_url(
            admin_url('admin-post.php?action=bbai_debug_export'),
            'bbai_debug_export'
        );
        
        // Get plan info for upgrade card
        $plan_slug = isset($usage_stats) && isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
        $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
        $is_free = ($plan_slug === 'free');
        $is_growth = ($plan_slug === 'pro' || $plan_slug === 'growth');
        $is_agency = ($plan_slug === 'agency');
    ?>
    <div class="bbai-dashboard-container" data-bbai-debug-panel>
        <div class="bbai-page-header bbai-mb-6">
            <div class="bbai-page-header-content">
                <h1 class="bbai-page-title"><?php esc_html_e('Debug Logs', 'opptiai-alt'); ?></h1>
                <p class="bbai-page-subtitle"><?php esc_html_e('API calls, queue events, and error logs', 'opptiai-alt'); ?></p>
            </div>
            <div class="bbai-btn-group">
                <a href="<?php echo esc_url($debug_export_url); ?>" class="bbai-btn bbai-btn-secondary">
                    <?php esc_html_e('Export CSV', 'opptiai-alt'); ?>
                </a>
                <button type="button" class="bbai-btn bbai-btn-secondary" data-debug-clear>
                    <?php esc_html_e('Clear Logs', 'opptiai-alt'); ?>
                </button>
            </div>
        </div>

        <section class="bbai-grid bbai-mb-6" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div class="bbai-stat-card">
                <div class="bbai-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M4 6H20M4 12H20M4 18H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-stat-value" data-debug-stat="total">
                    <?php echo esc_html(number_format_i18n(intval($debug_stats['total'] ?? 0))); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Total Logs', 'opptiai-alt'); ?></div>
            </div>
            <div class="bbai-stat-card">
                <div class="bbai-stat-icon" style="background: var(--bbai-warning-bg); color: var(--bbai-warning);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M12 3L2 21H22L12 3Z" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M12 9V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="17" r="1" fill="currentColor"/>
                    </svg>
                </div>
                <div class="bbai-stat-value bbai-text-warning" data-debug-stat="warnings">
                    <?php echo esc_html(number_format_i18n(intval($debug_stats['warnings'] ?? 0))); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Warnings', 'opptiai-alt'); ?></div>
            </div>
            <div class="bbai-stat-card">
                <div class="bbai-stat-icon" style="background: var(--bbai-error-bg); color: var(--bbai-error);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M9 9L15 15M15 9L9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-stat-value bbai-text-error" data-debug-stat="errors">
                    <?php echo esc_html(number_format_i18n(intval($debug_stats['errors'] ?? 0))); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Errors', 'opptiai-alt'); ?></div>
            </div>
            <div class="bbai-stat-card">
                <div class="bbai-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M12 7V12L15 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-stat-value bbai-text-secondary bbai-text-lg" data-debug-stat="last_api">
                    <?php echo esc_html($debug_stats['last_event'] ?? $debug_stats['last_api'] ?? '—'); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Last Event Timestamp', 'opptiai-alt'); ?></div>
            </div>
        </section>

        <form data-debug-filter class="bbai-filter-form bbai-mb-6">
            <div>
                <label for="bbai-debug-level" class="bbai-filter-label"><?php esc_html_e('Level', 'opptiai-alt'); ?></label>
                <select id="bbai-debug-level" name="level" class="bbai-filter-select">
                    <option value=""><?php esc_html_e('All levels', 'opptiai-alt'); ?></option>
                    <option value="debug"><?php esc_html_e('Debug', 'opptiai-alt'); ?></option>
                    <option value="info"><?php esc_html_e('Info', 'opptiai-alt'); ?></option>
                    <option value="warning"><?php esc_html_e('Warning', 'opptiai-alt'); ?></option>
                    <option value="error"><?php esc_html_e('Error', 'opptiai-alt'); ?></option>
                </select>
            </div>
            <div>
                <label class="bbai-filter-label"><?php esc_html_e('Date Range', 'opptiai-alt'); ?></label>
                <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--bbai-space-2);">
                    <input type="date" id="bbai-debug-date-from" name="date_from" class="bbai-filter-input" aria-label="<?php esc_attr_e('Start date', 'opptiai-alt'); ?>">
                    <input type="date" id="bbai-debug-date-to" name="date_to" class="bbai-filter-input" aria-label="<?php esc_attr_e('End date', 'opptiai-alt'); ?>">
                </div>
            </div>
            <div>
                <label for="bbai-debug-search" class="bbai-filter-label"><?php esc_html_e('Search', 'opptiai-alt'); ?></label>
                <input type="search" id="bbai-debug-search" name="search" class="bbai-filter-input" placeholder="<?php esc_attr_e('Search logs', 'opptiai-alt'); ?>">
            </div>
            <div class="bbai-filter-submit">
                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-debug-reset>
                    <?php esc_html_e('Reset', 'opptiai-alt'); ?>
                </button>
            </div>
        </form>

        <div class="bbai-card bbai-mb-6">
            <div class="bbai-table-wrap">
                <table class="bbai-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'opptiai-alt'); ?></th>
                            <th><?php esc_html_e('Level', 'opptiai-alt'); ?></th>
                            <th><?php esc_html_e('Message', 'opptiai-alt'); ?></th>
                            <th><?php esc_html_e('Context', 'opptiai-alt'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-debug-rows>
                        <?php if (!empty($debug_logs)) : ?>
                            <?php
                            $row_index = 0;
                            foreach ($debug_logs as $log) :
                                $row_index++;
                                $level = strtolower($log['level'] ?? 'info');
                                $level_slug = preg_replace('/[^a-z0-9_-]/i', '', $level) ?: 'info';
                                $context_attr = '';
                                $context_source = $log['context'] ?? [];
                                if (!empty($context_source)) {
                                    if (is_array($context_source)) {
                                        $json = wp_json_encode($context_source);
                                        $context_attr = base64_encode($json);
                                    } else {
                                        $context_str = (string) $context_source;
                                        if ( ! function_exists( 'bbai_json_decode_array' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
                                            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
                                        }
                                        $decoded = function_exists( 'bbai_json_decode_array' ) ? bbai_json_decode_array( $context_str ) : null;
                                        if (is_array($decoded)) {
                                            $json = wp_json_encode($decoded);
                                            $context_attr = base64_encode($json);
                                        } else {
                                            $context_attr = base64_encode($context_str);
                                        }
                                    }
                                }

                                $formatted_date = $log['created_at'] ?? '';
                                $badge_variant = 'info';
                                if ($level_slug === 'warning') {
                                    $badge_variant = 'warning';
                                } elseif ($level_slug === 'error') {
                                    $badge_variant = 'error';
                                } elseif ($level_slug === 'debug') {
                                    $badge_variant = 'pending';
                                }
                                $badge_class = 'bbai-status-badge bbai-status-badge--' . $badge_variant;
                            ?>
                            <tr data-row-index="<?php echo esc_attr($row_index); ?>">
                                <td class="bbai-text-muted"><?php echo esc_html($formatted_date); ?></td>
                                <td>
                                    <span class="<?php echo esc_attr($badge_class); ?>">
                                        <?php echo esc_html(ucfirst($level_slug)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['message'] ?? ''); ?></td>
                                <td>
                                    <?php if ($context_attr) : ?>
                                        <button type="button"
                                                class="bbai-btn bbai-btn-secondary bbai-btn-sm"
                                                data-debug-context
                                                data-context-data="<?php echo esc_attr($context_attr); ?>"
                                                data-row-index="<?php echo esc_attr($row_index); ?>"
                                                aria-expanded="false">
                                            <?php esc_html_e('Log Context', 'opptiai-alt'); ?>
                                        </button>
                                    <?php else : ?>
                                        <span class="bbai-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($context_attr) : ?>
                            <tr class="bbai-debug-context-row" data-row-index="<?php echo esc_attr($row_index); ?>">
                                <td colspan="4" class="bbai-p-2">
                                    <div class="bbai-card bbai-card--compact">
                                        <pre class="bbai-text-sm" style="font-family: var(--bbai-font-mono); white-space: pre-wrap; word-break: break-word; margin: 0;"></pre>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4" class="bbai-text-center bbai-text-muted bbai-text-sm">
                                    <?php esc_html_e('No logs recorded yet.', 'opptiai-alt'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($debug_pages > 1) : ?>
        <div class="bbai-pagination bbai-mb-6">
            <span class="bbai-pagination-info" data-debug-page-indicator>
                <?php
                    printf(
                        /* translators: 1: current page number, 2: total pages */
                        esc_html__('Page %1$s of %2$s', 'opptiai-alt'),
                        esc_html(number_format_i18n($debug_page)),
                        esc_html(number_format_i18n($debug_pages))
                    );
                ?>
            </span>
            <div class="bbai-pagination-controls">
                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-pagination-btn <?php echo esc_attr( $debug_page <= 1 ? 'bbai-pagination-btn--disabled' : '' ); ?>" data-debug-page="prev" <?php disabled($debug_page <= 1); ?>>
                    <?php esc_html_e('Previous', 'opptiai-alt'); ?>
                </button>
                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-pagination-btn <?php echo esc_attr( $debug_page >= $debug_pages ? 'bbai-pagination-btn--disabled' : '' ); ?>" data-debug-page="next" <?php disabled($debug_page >= $debug_pages); ?>>
                    <?php esc_html_e('Next', 'opptiai-alt'); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="bbai-debug-toast" data-debug-toast hidden></div>
    </div>
    
    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    $bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
    ?>
    
<?php endif; ?>
