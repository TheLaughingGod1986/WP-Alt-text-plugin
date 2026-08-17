<?php
/**
 * Debug Logs tab content partial.
 *
 * Expects $this (core class), $debug_bootstrap, and $bbai_usage_stats in scope.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Restrict visibility to administrators or WP_DEBUG environments.
$bbai_can_view_debug = (defined('WP_DEBUG') && WP_DEBUG) || current_user_can('manage_options');

// Check authentication - accept either JWT token OR active license.
$bbai_is_authenticated = $this->api_client->is_authenticated();
$bbai_has_license = $this->api_client->has_active_license();
$bbai_can_access_debug = $bbai_is_authenticated || $bbai_has_license;
$bbai_debug_embedded = !empty($bbai_debug_embedded);
?>
<?php if (!$bbai_can_view_debug) : ?>
    <div class="bbai-debug-page">
        <div class="notice notice-info">
            <p><?php esc_html_e('Debug Logs are only visible when WP_DEBUG is enabled or for administrators.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>
    </div>
<?php elseif (!$bbai_can_access_debug) : ?>
    <div class="bbai-debug-page">
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
                    <button type="button" class="bbai-btn bbai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
                        <svg class="bbai-btn-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <circle cx="8" cy="8" r="2" fill="currentColor"/>
                        </svg>
                        <span class="bbai-btn__text"><?php esc_html_e('Log In', 'beepbeep-ai-alt-text-generator'); ?></span>
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
        $bbai_system_status    = $debug_bootstrap['system_status'] ?? [];
        $bbai_service_status   = $debug_bootstrap['service_status'] ?? [];
        $bbai_recent_errors    = $debug_bootstrap['recent_errors'] ?? [];
        $bbai_copy_debug_info  = $debug_bootstrap['copy_debug_info'] ?? [];
        $bbai_debug_page       = max(1, intval($bbai_debug_pagination['page'] ?? 1));
        $bbai_debug_pages      = max(1, intval($bbai_debug_pagination['total_pages'] ?? 1));
        $bbai_debug_export_url = wp_nonce_url(
            admin_url('admin-post.php?action=beepbeepai_debug_export'),
            'bbai_debug_export'
        );
        $bbai_copy_debug_attr = '';
        if (!empty($bbai_copy_debug_info)) {
            $bbai_copy_debug_attr = base64_encode(wp_json_encode($bbai_copy_debug_info));
        }

        $bbai_connection_status = strtolower((string)($bbai_service_status['connection_status'] ?? 'failed'));
        $bbai_connection_badge  = 'connected' === $bbai_connection_status ? 'bbai-status-badge--success' : 'bbai-status-badge--error';
        $bbai_connection_label  = 'connected' === $bbai_connection_status
            ? __('Connected', 'beepbeep-ai-alt-text-generator')
            : __('Failed', 'beepbeep-ai-alt-text-generator');

        $bbai_avg_response_ms = isset($bbai_service_status['average_response_time_ms'])
            ? intval($bbai_service_status['average_response_time_ms'])
            : 0;
        $bbai_avg_response_text = $bbai_avg_response_ms > 0
            ? sprintf(
                /* translators: %d: response time in milliseconds */
                esc_html__('%d ms', 'beepbeep-ai-alt-text-generator'),
                intval($bbai_avg_response_ms)
            )
            : '—';
    ?>
    <style>
        [data-bbai-debug-panel] .bbai-debug-support-grid { display: grid; gap: var(--bbai-space-4); grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        [data-bbai-debug-panel] .bbai-debug-status-list { margin: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--bbai-space-3); }
        [data-bbai-debug-panel] .bbai-debug-status-item { border: 1px solid var(--bbai-border); border-radius: var(--bbai-radius); padding: var(--bbai-space-3); background: var(--bbai-bg-secondary); }
        [data-bbai-debug-panel] .bbai-debug-status-item dt { margin: 0 0 var(--bbai-space-1); color: var(--bbai-text-muted); font-size: var(--bbai-text-xs); font-weight: var(--bbai-font-semibold); text-transform: uppercase; letter-spacing: 0.03em; }
        [data-bbai-debug-panel] .bbai-debug-status-item dd { margin: 0; color: var(--bbai-text); font-weight: var(--bbai-font-medium); word-break: break-word; }
        [data-bbai-debug-panel] .bbai-debug-service-metric { display: flex; align-items: center; justify-content: space-between; padding: var(--bbai-space-3) 0; border-bottom: 1px solid var(--bbai-border); gap: var(--bbai-space-3); }
        [data-bbai-debug-panel] .bbai-debug-service-metric:last-child { border-bottom: 0; }
        [data-bbai-debug-panel] .bbai-debug-service-metric-label { font-size: var(--bbai-text-sm); color: var(--bbai-text-muted); }
        [data-bbai-debug-panel] .bbai-debug-service-metric-value { font-size: var(--bbai-text-sm); color: var(--bbai-text); font-weight: var(--bbai-font-semibold); text-align: right; }
        [data-bbai-debug-panel] .bbai-debug-recent-errors__row--error td { background: var(--bbai-error-bg); }
        [data-bbai-debug-panel] .bbai-debug-recent-errors__context { color: var(--bbai-text-muted); font-family: var(--bbai-font-mono); font-size: var(--bbai-text-xs); }
        [data-bbai-debug-panel] .bbai-debug-modal[hidden] { display: none !important; }
        [data-bbai-debug-panel] .bbai-debug-modal { position: fixed; inset: 0; z-index: 100001; display: flex; align-items: center; justify-content: center; padding: var(--bbai-space-4); }
        [data-bbai-debug-panel] .bbai-debug-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.6); }
        [data-bbai-debug-panel] .bbai-debug-modal__dialog { position: relative; z-index: 1; width: min(1000px, 95vw); max-height: 90vh; overflow: auto; background: var(--bbai-bg); border: 1px solid var(--bbai-border); border-radius: var(--bbai-radius-lg); box-shadow: var(--bbai-shadow-2xl); }
        [data-bbai-debug-panel] .bbai-debug-modal__header { display: flex; justify-content: space-between; align-items: center; gap: var(--bbai-space-3); padding: var(--bbai-space-4); border-bottom: 1px solid var(--bbai-border); }
        [data-bbai-debug-panel] .bbai-debug-modal__title { margin: 0; font-size: var(--bbai-text-lg); }
        [data-bbai-debug-panel] .bbai-debug-modal__meta { margin: 0; color: var(--bbai-text-muted); font-size: var(--bbai-text-sm); }
        [data-bbai-debug-panel] .bbai-debug-modal__body { padding: var(--bbai-space-4); display: grid; gap: var(--bbai-space-4); }
        [data-bbai-debug-panel] .bbai-debug-modal__section h3 { margin: 0 0 var(--bbai-space-2); font-size: var(--bbai-text-sm); text-transform: uppercase; letter-spacing: 0.04em; color: var(--bbai-text-muted); }
        [data-bbai-debug-panel] .bbai-debug-modal__pre { margin: 0; font-family: var(--bbai-font-mono); font-size: 12px; white-space: pre-wrap; word-break: break-word; background: var(--bbai-bg-secondary); border: 1px solid var(--bbai-border); border-radius: var(--bbai-radius); padding: var(--bbai-space-3); max-height: 260px; overflow: auto; }
        @media (max-width: 782px) {
            [data-bbai-debug-panel] .bbai-page-header { align-items: flex-start; }
            [data-bbai-debug-panel] .bbai-btn-group { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--bbai-space-2); }
            [data-bbai-debug-panel] .bbai-btn-group .bbai-btn { width: 100%; text-align: center; }
        }
    </style>
    <div class="bbai-debug-page" data-bbai-debug-panel>
        <div class="bbai-page-header bbai-mb-6<?php echo $bbai_debug_embedded ? ' bbai-page-header--embedded' : ''; ?>">
            <div class="bbai-page-header-content">
                <?php if ($bbai_debug_embedded) : ?>
                <h2 class="bbai-page-title"><?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <?php else : ?>
                <h1 class="bbai-page-title"><?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?></h1>
                <?php endif; ?>
                <p class="bbai-page-subtitle"><?php esc_html_e('Support-first troubleshooting for API calls, queue events, and failures.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <div class="bbai-btn-group">
                <a href="<?php echo esc_url($bbai_debug_export_url); ?>" class="bbai-btn bbai-btn-secondary">
                    <?php esc_html_e('Export CSV', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <button type="button" class="bbai-btn bbai-btn-secondary" data-debug-copy-info data-copy-debug="<?php echo esc_attr($bbai_copy_debug_attr); ?>">
                    <?php esc_html_e('Copy Debug Info', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-btn bbai-btn-secondary" data-debug-clear>
                    <?php esc_html_e('Clear Logs', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>

        <section class="bbai-card bbai-mb-6">
            <div class="bbai-card-header">
                <h2 class="bbai-card-title"><?php esc_html_e('System Status', 'beepbeep-ai-alt-text-generator'); ?></h2>
            </div>
            <dl class="bbai-debug-status-list">
                <div class="bbai-debug-status-item">
                    <dt><?php esc_html_e('Plugin Version', 'beepbeep-ai-alt-text-generator'); ?></dt>
                    <dd data-debug-system="plugin_version"><?php echo esc_html($bbai_system_status['plugin_version'] ?? '—'); ?></dd>
                </div>
                <div class="bbai-debug-status-item">
                    <dt><?php esc_html_e('WordPress Version', 'beepbeep-ai-alt-text-generator'); ?></dt>
                    <dd data-debug-system="wordpress_version"><?php echo esc_html($bbai_system_status['wordpress_version'] ?? '—'); ?></dd>
                </div>
                <div class="bbai-debug-status-item">
                    <dt><?php esc_html_e('PHP Version', 'beepbeep-ai-alt-text-generator'); ?></dt>
                    <dd data-debug-system="php_version"><?php echo esc_html($bbai_system_status['php_version'] ?? '—'); ?></dd>
                </div>
                <div class="bbai-debug-status-item">
                    <dt><?php esc_html_e('Active Theme', 'beepbeep-ai-alt-text-generator'); ?></dt>
                    <dd data-debug-system="active_theme"><?php echo esc_html($bbai_system_status['active_theme'] ?? '—'); ?></dd>
                </div>
                <div class="bbai-debug-status-item">
                    <dt><?php esc_html_e('Site URL', 'beepbeep-ai-alt-text-generator'); ?></dt>
                    <dd data-debug-system="site_url"><?php echo esc_html($bbai_system_status['site_url'] ?? '—'); ?></dd>
                </div>
            </dl>
        </section>

        <section class="bbai-debug-support-grid bbai-mb-6">
            <div class="bbai-card">
                <div class="bbai-card-header">
                    <h2 class="bbai-card-title"><?php esc_html_e('AI Service Status', 'beepbeep-ai-alt-text-generator'); ?></h2>
                </div>
                <div class="bbai-card-body">
                    <div class="bbai-debug-service-metric">
                        <span class="bbai-debug-service-metric-label"><?php esc_html_e('Connection', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-status-badge <?php echo esc_attr($bbai_connection_badge); ?>" data-debug-service="connection"><?php echo esc_html($bbai_connection_label); ?></span>
                    </div>
                    <div class="bbai-debug-service-metric">
                        <span class="bbai-debug-service-metric-label"><?php esc_html_e('Last API Request', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-debug-service-metric-value" data-debug-service="last_request"><?php echo esc_html($bbai_service_status['last_api_request'] ?? '—'); ?></span>
                    </div>
                    <div class="bbai-debug-service-metric">
                        <span class="bbai-debug-service-metric-label"><?php esc_html_e('Average Response Time', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-debug-service-metric-value" data-debug-service="avg_response"><?php echo esc_html($bbai_avg_response_text); ?></span>
                    </div>
                </div>
            </div>
        </section>

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

        <section class="bbai-card bbai-mb-6" data-debug-recent-errors-section <?php if (empty($bbai_recent_errors)) : ?>hidden<?php endif; ?>>
            <div class="bbai-card-header bbai-table-header">
                <h2 class="bbai-card-title"><?php esc_html_e('Recent Errors', 'beepbeep-ai-alt-text-generator'); ?></h2>
            </div>
            <div class="bbai-table-wrap bbai-table__scroll">
                <table class="bbai-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Level', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Message', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Context', 'beepbeep-ai-alt-text-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-debug-recent-errors>
                        <?php if (!empty($bbai_recent_errors)) : ?>
                            <?php foreach ($bbai_recent_errors as $bbai_recent_error) : ?>
                                <?php
                                    $bbai_recent_level = strtolower((string)($bbai_recent_error['level'] ?? 'warning'));
                                    $bbai_recent_badge = 'error' === $bbai_recent_level ? 'bbai-status-badge--error' : 'bbai-status-badge--warning';
                                    $bbai_recent_row_class = 'error' === $bbai_recent_level ? 'bbai-debug-recent-errors__row--error' : '';
                                    $bbai_recent_context = $bbai_recent_error['context'] ?? [];
                                    $bbai_recent_context_preview = '—';
                                    if (is_array($bbai_recent_context) && !empty($bbai_recent_context)) {
                                        $bbai_recent_context_preview = (string) wp_json_encode($bbai_recent_context);
                                    } elseif (is_string($bbai_recent_context) && $bbai_recent_context !== '') {
                                        $bbai_recent_context_preview = $bbai_recent_context;
                                    }
                                    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                                        if (mb_strlen($bbai_recent_context_preview) > 180) {
                                            $bbai_recent_context_preview = mb_substr($bbai_recent_context_preview, 0, 180) . '...';
                                        }
                                    } elseif (strlen($bbai_recent_context_preview) > 180) {
                                        $bbai_recent_context_preview = substr($bbai_recent_context_preview, 0, 180) . '...';
                                    }
                                ?>
                                <tr class="<?php echo esc_attr($bbai_recent_row_class); ?>">
                                    <td class="bbai-text-muted"><?php echo esc_html($bbai_recent_error['created_at'] ?? ''); ?></td>
                                    <td>
                                        <span class="bbai-status-badge <?php echo esc_attr($bbai_recent_badge); ?>">
                                            <?php echo esc_html(ucfirst($bbai_recent_level)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($bbai_recent_error['message'] ?? ''); ?></td>
                                    <td class="bbai-debug-recent-errors__context"><?php echo esc_html($bbai_recent_context_preview); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                                <tr class="bbai-row">
                                    <td colspan="4" class="bbai-text-center bbai-text-muted bbai-text-sm bbai-table-empty bbai-table-empty--cell">
                                        <?php esc_html_e('No warnings or errors recorded yet.', 'beepbeep-ai-alt-text-generator'); ?>
                                    </td>
                                </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <form data-debug-filter class="bbai-filter-form bbai-mb-6">
            <div>
                <label for="bbai-debug-level" class="bbai-filter-label"><?php esc_html_e('Level', 'beepbeep-ai-alt-text-generator'); ?></label>
                <select id="bbai-debug-level" name="level" class="bbai-filter-select bbai-select">
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
                    <input type="date" id="bbai-debug-date-from" name="date_from" class="bbai-filter-input bbai-input" aria-label="<?php esc_attr_e('Start date', 'beepbeep-ai-alt-text-generator'); ?>">
                    <input type="date" id="bbai-debug-date-to" name="date_to" class="bbai-filter-input bbai-input" aria-label="<?php esc_attr_e('End date', 'beepbeep-ai-alt-text-generator'); ?>">
                </div>
            </div>
            <div>
                <label for="bbai-debug-search" class="bbai-filter-label"><?php esc_html_e('Search', 'beepbeep-ai-alt-text-generator'); ?></label>
                <input type="search" id="bbai-debug-search" name="search" class="bbai-filter-input bbai-input bbai-input--search" placeholder="<?php esc_attr_e('Search logs', 'beepbeep-ai-alt-text-generator'); ?>">
            </div>
            <div class="bbai-filter-submit">
                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-debug-reset>
                    <?php esc_html_e('Reset', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </form>

        <div class="bbai-card bbai-mb-6">
            <div class="bbai-table-wrap bbai-table__scroll">
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
                            <?php foreach ($bbai_debug_logs as $bbai_log) : ?>
                                <?php
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
                                            if (!function_exists('bbai_json_decode_array') && defined('BEEPBEEP_AI_PLUGIN_DIR')) {
                                                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
                                            }
                                            $bbai_decoded = function_exists('bbai_json_decode_array') ? bbai_json_decode_array($bbai_context_str) : null;
                                            if (is_array($bbai_decoded)) {
                                                $bbai_json = wp_json_encode($bbai_decoded);
                                                $bbai_context_attr = base64_encode($bbai_json);
                                            } else {
                                                $bbai_context_attr = base64_encode($bbai_context_str);
                                            }
                                        }
                                    }

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
                                <tr>
                                    <td class="bbai-text-muted"><?php echo esc_html($bbai_log['created_at'] ?? ''); ?></td>
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
                                                    data-log-level="<?php echo esc_attr($bbai_level_slug); ?>"
                                                    data-log-message="<?php echo esc_attr((string) ($bbai_log['message'] ?? '')); ?>"
                                                    data-log-time="<?php echo esc_attr((string) ($bbai_log['created_at'] ?? '')); ?>">
                                                <?php esc_html_e('View Details', 'beepbeep-ai-alt-text-generator'); ?>
                                            </button>
                                        <?php else : ?>
                                            <span class="bbai-text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="bbai-row">
                                <td colspan="4" class="bbai-text-center bbai-text-muted bbai-text-sm bbai-table-empty bbai-table-empty--cell">
                                    <?php esc_html_e('No logs recorded yet.', 'beepbeep-ai-alt-text-generator'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($bbai_debug_pages > 1) : ?>
        <div class="bbai-pagination bbai-table-meta bbai-mb-6">
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

        <div class="bbai-debug-modal" data-debug-modal hidden>
            <div class="bbai-debug-modal__backdrop" data-debug-modal-close></div>
            <div class="bbai-debug-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bbai-debug-modal-title">
                <div class="bbai-debug-modal__header">
                    <div>
                        <h2 id="bbai-debug-modal-title" class="bbai-debug-modal__title"><?php esc_html_e('Log Context Details', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-debug-modal__meta" data-debug-modal-meta>—</p>
                    </div>
                    <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-debug-modal-close>
                        <?php esc_html_e('Close', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
                <div class="bbai-debug-modal__body">
                    <section class="bbai-debug-modal__section">
                        <h3><?php esc_html_e('Full Request Payload', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <pre class="bbai-debug-modal__pre" data-debug-modal-request></pre>
                    </section>
                    <section class="bbai-debug-modal__section">
                        <h3><?php esc_html_e('Full Response Payload', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <pre class="bbai-debug-modal__pre" data-debug-modal-response></pre>
                    </section>
                    <section class="bbai-debug-modal__section">
                        <h3><?php esc_html_e('Stack Trace', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <pre class="bbai-debug-modal__pre" data-debug-modal-stack></pre>
                    </section>
                </div>
            </div>
        </div>

        <div class="bbai-debug-toast" data-debug-toast hidden></div>
    </div>
<?php endif; ?>
