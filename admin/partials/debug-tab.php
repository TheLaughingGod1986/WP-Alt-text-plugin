<?php
/**
 * Debug Logs tab content partial.
 *
 * Expects $this (core class), $debug_bootstrap, and $bbai_usage_stats in scope.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php
// Check authentication - accept either JWT token OR active license
$bbai_is_authenticated = $this->api_client->is_authenticated();
$bbai_has_license = $this->api_client->has_active_license();
$bbai_can_access_debug = $bbai_is_authenticated || $bbai_has_license;

if (!$bbai_can_access_debug) : ?>
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
                <h2><?php esc_html_e('Authentication Required', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p><?php esc_html_e('Debug Logs are available to all authenticated users. Please log in or enter your license key to access this section.', 'beepbeep-ai-alt-text-generator'); ?></p>
                <p class="bbai-settings-required-note">
                    <?php esc_html_e('If you don\'t have an account, you can create one for free or subscribe to a plan.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
                <div class="bbai-settings-required-actions">
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-icon" data-action="show-auth-modal" data-auth-tab="login">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <circle cx="8" cy="8" r="2" fill="currentColor"/>
                        </svg>
                        <span><?php esc_html_e('Log In', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) : ?>
    <div class="bbai-section">
        <div class="notice notice-warning">
            <p><?php esc_html_e('Debug logging is not available on this site. Please reinstall the logging module.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>
    </div>
<?php else : ?>
    <?php
        $bbai_debug_logs       = $debug_bootstrap['logs'] ?? [];
        $bbai_debug_stats      = $debug_bootstrap['stats'] ?? [];
        $bbai_debug_pagination = $debug_bootstrap['pagination'] ?? [];
        $bbai_debug_page       = max(1, intval($bbai_debug_pagination['page'] ?? 1));
        $bbai_debug_pages      = max(1, intval($bbai_debug_pagination['total_pages'] ?? 1));
        $bbai_debug_export_url = wp_nonce_url(
            admin_url('admin-post.php?action=bbai_debug_export'),
            'bbai_debug_export'
        );
        
        // Get plan info for upgrade card
        $bbai_plan_slug = isset($bbai_usage_stats) && isset($bbai_usage_stats['plan']) ? $bbai_usage_stats['plan'] : 'free';
        $bbai_is_pro = ($bbai_plan_slug === 'pro' || $bbai_plan_slug === 'agency');
        $bbai_is_free = ($bbai_plan_slug === 'free');
        $bbai_is_growth = ($bbai_plan_slug === 'pro' || $bbai_plan_slug === 'growth');
        $bbai_is_agency = ($bbai_plan_slug === 'agency');
    ?>
    <div class="bbai-dashboard-container" data-bbai-debug-panel>
        <div class="bbai-page-header bbai-mb-6">
            <div class="bbai-page-header-content">
                <h1 class="bbai-page-title"><?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?></h1>
                <p class="bbai-page-subtitle"><?php esc_html_e('API calls, queue events, and error logs', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <div class="bbai-btn-group">
                <a href="<?php echo esc_url($bbai_debug_export_url); ?>" class="bbai-btn bbai-btn-secondary">
                    <?php esc_html_e('Export CSV', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <button type="button" class="bbai-btn bbai-btn-secondary" data-debug-clear>
                    <?php esc_html_e('Clear Logs', 'beepbeep-ai-alt-text-generator'); ?>
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
                    <?php echo esc_html(number_format_i18n(intval($bbai_debug_stats['total'] ?? 0))); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Total Logs', 'beepbeep-ai-alt-text-generator'); ?></div>
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
                    <?php echo esc_html(number_format_i18n(intval($bbai_debug_stats['warnings'] ?? 0))); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Warnings', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
            <div class="bbai-stat-card">
                <div class="bbai-stat-icon" style="background: var(--bbai-error-bg); color: var(--bbai-error);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M9 9L15 15M15 9L9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-stat-value bbai-text-error" data-debug-stat="errors">
                    <?php echo esc_html(number_format_i18n(intval($bbai_debug_stats['errors'] ?? 0))); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Errors', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
            <div class="bbai-stat-card">
                <div class="bbai-stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M12 7V12L15 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-stat-value bbai-text-secondary bbai-text-lg" data-debug-stat="last_api">
                    <?php echo esc_html($bbai_debug_stats['last_event'] ?? $bbai_debug_stats['last_api'] ?? '—'); ?>
                </div>
                <div class="bbai-stat-label"><?php esc_html_e('Last Event Timestamp', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
        </section>

        <form data-debug-filter class="bbai-filter-form bbai-mb-6">
            <div>
                <label for="bbai-debug-level" class="bbai-filter-label"><?php esc_html_e('Level', 'beepbeep-ai-alt-text-generator'); ?></label>
                <select id="bbai-debug-level" name="level" class="bbai-filter-select">
                    <option value=""><?php esc_html_e('All levels', 'beepbeep-ai-alt-text-generator'); ?></option>
                    <option value="debug"><?php esc_html_e('Debug', 'beepbeep-ai-alt-text-generator'); ?></option>
                    <option value="info"><?php esc_html_e('Info', 'beepbeep-ai-alt-text-generator'); ?></option>
                    <option value="warning"><?php esc_html_e('Warning', 'beepbeep-ai-alt-text-generator'); ?></option>
                    <option value="error"><?php esc_html_e('Error', 'beepbeep-ai-alt-text-generator'); ?></option>
                </select>
            </div>
            <div>
                <label class="bbai-filter-label"><?php esc_html_e('Date Range', 'beepbeep-ai-alt-text-generator'); ?></label>
                <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--bbai-space-2);">
                    <input type="date" id="bbai-debug-date-from" name="date_from" class="bbai-filter-input" aria-label="<?php esc_attr_e('Start date', 'beepbeep-ai-alt-text-generator'); ?>">
                    <input type="date" id="bbai-debug-date-to" name="date_to" class="bbai-filter-input" aria-label="<?php esc_attr_e('End date', 'beepbeep-ai-alt-text-generator'); ?>">
                </div>
            </div>
            <div>
                <label for="bbai-debug-search" class="bbai-filter-label"><?php esc_html_e('Search', 'beepbeep-ai-alt-text-generator'); ?></label>
                <input type="search" id="bbai-debug-search" name="search" class="bbai-filter-input" placeholder="<?php esc_attr_e('Search logs', 'beepbeep-ai-alt-text-generator'); ?>">
            </div>
            <div class="bbai-filter-submit">
                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-debug-reset>
                    <?php esc_html_e('Reset', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </form>

        <div class="bbai-card bbai-mb-6">
            <div class="bbai-table-wrap">
                <table class="bbai-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Level', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Message', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Context', 'beepbeep-ai-alt-text-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-debug-rows>
                        <?php if (!empty($bbai_debug_logs)) : ?>
                            <?php
                            $bbai_row_index = 0;
                            foreach ($bbai_debug_logs as $bbai_log) :
                                $bbai_row_index++;
                                $bbai_level = strtolower($bbai_log['level'] ?? 'info');
                                $bbai_level_slug = preg_replace('/[^a-z0-9_-]/i', '', $bbai_level) ?: 'info';
                                $bbai_context_attr = '';
                                $bbai_context_source = $bbai_log['context'] ?? [];
                                if (!empty($bbai_context_source)) {
                                    if (is_array($bbai_context_source)) {
                                        $bbai_json = wp_json_encode($bbai_context_source);
                                        $bbai_context_attr = base64_encode($bbai_json);
                                    } else {
                                        $bbai_context_str = (string) $bbai_context_source;
                                        if ( ! function_exists( 'bbai_json_decode_array' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
                                            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
                                        }
                                        $bbai_decoded = function_exists( 'bbai_json_decode_array' ) ? bbai_json_decode_array( $bbai_context_str ) : null;
                                        if (is_array($bbai_decoded)) {
                                            $bbai_json = wp_json_encode($bbai_decoded);
                                            $bbai_context_attr = base64_encode($bbai_json);
                                        } else {
                                            $bbai_context_attr = base64_encode($bbai_context_str);
                                        }
                                    }
                                }

                                $bbai_formatted_date = $bbai_log['created_at'] ?? '';
                                $bbai_badge_variant = 'info';
                                if ($bbai_level_slug === 'warning') {
                                    $bbai_badge_variant = 'warning';
                                } elseif ($bbai_level_slug === 'error') {
                                    $bbai_badge_variant = 'error';
                                } elseif ($bbai_level_slug === 'debug') {
                                    $bbai_badge_variant = 'pending';
                                }
                                $bbai_badge_class = 'bbai-status-badge bbai-status-badge--' . $bbai_badge_variant;
                            ?>
                            <tr data-row-index="<?php echo esc_attr($bbai_row_index); ?>">
                                <td class="bbai-text-muted"><?php echo esc_html($bbai_formatted_date); ?></td>
                                <td>
                                    <span class="<?php echo esc_attr($bbai_badge_class); ?>">
                                        <?php echo esc_html(ucfirst($bbai_level_slug)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($bbai_log['message'] ?? ''); ?></td>
                                <td>
                                    <?php if ($bbai_context_attr) : ?>
                                        <button type="button"
                                                class="bbai-btn bbai-btn-secondary bbai-btn-sm"
                                                data-debug-context
                                                data-context-data="<?php echo esc_attr($bbai_context_attr); ?>"
                                                data-row-index="<?php echo esc_attr($bbai_row_index); ?>"
                                                aria-expanded="false">
                                            <?php esc_html_e('Log Context', 'beepbeep-ai-alt-text-generator'); ?>
                                        </button>
                                    <?php else : ?>
                                        <span class="bbai-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($bbai_context_attr) : ?>
                            <tr class="bbai-debug-context-row" data-row-index="<?php echo esc_attr($bbai_row_index); ?>">
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
                                    <?php esc_html_e('No logs recorded yet.', 'beepbeep-ai-alt-text-generator'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($bbai_debug_pages > 1) : ?>
        <div class="bbai-pagination bbai-mb-6">
            <span class="bbai-pagination-info" data-debug-page-indicator>
                <?php
                    printf(
                        /* translators: 1: current page number, 2: total pages */
                        esc_html__('Page %1$s of %2$s', 'beepbeep-ai-alt-text-generator'),
                        esc_html(number_format_i18n($bbai_debug_page)),
                        esc_html(number_format_i18n($bbai_debug_pages))
                    );
                ?>
            </span>
            <div class="bbai-pagination-controls">
                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-pagination-btn <?php echo esc_attr( $bbai_debug_page <= 1 ? 'bbai-pagination-btn--disabled' : '' ); ?>" data-debug-page="prev" <?php disabled($bbai_debug_page <= 1); ?>>
                    <?php esc_html_e('Previous', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-pagination-btn <?php echo esc_attr( $bbai_debug_page >= $bbai_debug_pages ? 'bbai-pagination-btn--disabled' : '' ); ?>" data-debug-page="next" <?php disabled($bbai_debug_page >= $bbai_debug_pages); ?>>
                    <?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="bbai-debug-toast" data-debug-toast hidden></div>
    </div>
    
    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    $bbai_bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bbai_bottom_upsell_partial)) {
        include $bbai_bottom_upsell_partial;
    }
    ?>
    
<?php endif; ?>
