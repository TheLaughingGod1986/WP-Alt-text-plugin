/**
 * Debug logs UI controller
 */
(function($) {
    'use strict';

    const config = window.BBAI_DEBUG || {};
    const $panel = $('[data-bbai-debug-panel]');

    if (!$panel.length) {
        return;
    }

    const strings = $.extend({
        noLogs: 'No logs recorded yet.',
        contextTitle: 'View Details',
        clearConfirm: 'This will permanently delete all debug logs. Continue?',
        errorGeneric: 'Unable to load debug logs. Please try again.',
        emptyContext: 'No additional context was provided for this entry.',
        emptyPayload: 'No request payload was captured for this entry.',
        emptyResponse: 'No response payload was captured for this entry.',
        emptyStack: 'No stack trace was captured for this entry.',
        modalTitle: 'Log Context Details',
        cleared: 'Logs cleared successfully.',
        copied: 'Debug info copied to clipboard.',
        copyFailed: 'Unable to copy debug info. Please copy it manually.',
        pageIndicator: 'Page %1$d of %2$d',
        connected: 'Connected',
        failed: 'Failed',
        noWarnings: 'No warnings or errors recorded yet.',
    }, config.strings || {});

    const state = {
        page: (config.initial && config.initial.pagination && config.initial.pagination.page) || 1,
        perPage: (config.initial && config.initial.pagination && config.initial.pagination.per_page) || 10,
        level: '',
        dateFrom: '',
        dateTo: '',
        search: '',
        useRestRouteFallback: false,
    };

    function init() {
        const normalizedLogsUrl = buildRestRouteFallback(config.restLogs);
        const normalizedClearUrl = buildRestRouteFallback(config.restClear);
        if (normalizedLogsUrl) {
            config.restLogs = normalizedLogsUrl;
            state.useRestRouteFallback = true;
        }
        if (normalizedClearUrl) {
            config.restClear = normalizedClearUrl;
            state.useRestRouteFallback = true;
        }

        bindEvents();

        if (config.initial) {
            render(config.initial);
        }
    }

    function bindEvents() {
        let searchTimer = null;

        $panel.on('submit', '[data-debug-filter]', function(event) {
            event.preventDefault();
            syncFilters();
            state.page = 1;
            fetchLogs();
        });

        $panel.on('change', '[data-debug-filter] select, [data-debug-filter] input[type="date"]', function() {
            syncFilters();
            state.page = 1;
            fetchLogs();
        });

        $panel.on('input', '[data-debug-filter] input[type="search"]', function() {
            syncFilters();
            state.page = 1;
            if (searchTimer) {
                clearTimeout(searchTimer);
            }
            searchTimer = setTimeout(fetchLogs, 300);
        });

        $panel.on('click', '[data-debug-reset]', function() {
            const $form = $panel.find('[data-debug-filter]');
            state.level = '';
            state.dateFrom = '';
            state.dateTo = '';
            state.search = '';
            state.page = 1;
            if ($form.length && $form[0].reset) {
                $form[0].reset();
            }
            if (searchTimer) {
                clearTimeout(searchTimer);
            }
            fetchLogs();
        });

        $panel.on('click', '[data-debug-clear]', function() {
            if (!confirm(strings.clearConfirm)) {
                return;
            }
            toggleLoading(true);
            ajaxWithRestFallback({
                url: config.restClear,
                method: 'POST',
                beforeSend: addNonce,
            })
            .done(function() {
                state.page = 1;
                fetchLogs();
                showToast(strings.cleared, 'success');
            })
            .fail(function() {
                toggleLoading(false);
                showToast(strings.errorGeneric, 'error');
            });
        });

        $panel.on('click', '[data-debug-copy-info]', function(event) {
            event.preventDefault();
            copyDebugInfo(this);
        });

        $panel.on('click', '[data-debug-page]', function() {
            const direction = $(this).data('debug-page');
            const pagination = $panel.data('debug-pagination') || {};
            const totalPages = Math.max(1, pagination.total_pages || 1);
            if (direction === 'prev' && state.page > 1) {
                state.page -= 1;
                fetchLogs();
            }
            if (direction === 'next' && state.page < totalPages) {
                state.page += 1;
                fetchLogs();
            }
        });

        $panel.on('click', '[data-debug-context]', function(event) {
            event.preventDefault();
            event.stopPropagation();
            openContextModal(this);
        });

        $panel.on('click', '[data-debug-modal-close]', function(event) {
            event.preventDefault();
            closeContextModal();
        });

        $(document).on('keydown.bbaiDebug', function(event) {
            if (event.key === 'Escape') {
                closeContextModal();
            }
        });
    }

    function syncFilters() {
        const $form = $panel.find('[data-debug-filter]');
        const rawLevel = $form.find('[name="level"]').val();
        state.level = typeof rawLevel === 'string' ? rawLevel.trim().toLowerCase() : '';
        state.dateFrom = $form.find('[name="date_from"]').val() || '';
        state.dateTo = $form.find('[name="date_to"]').val() || '';
        state.search = $form.find('[name="search"]').val() || '';
    }

    function copyDebugInfo(button) {
        const encoded = button.getAttribute('data-copy-debug') || '';
        const payload = decodeCopyPayload(encoded) || (config.initial && config.initial.copy_debug_info) || {};
        const formatted = formatCopyPayload(payload);

        copyTextToClipboard(formatted)
            .then(function() {
                showToast(strings.copied, 'success');
            })
            .catch(function() {
                showToast(strings.copyFailed, 'error');
            });
    }

    function decodeCopyPayload(encoded) {
        if (!encoded) {
            return null;
        }

        const attempts = [
            function(str) {
                try {
                    return JSON.parse(decodeURIComponent(escape(atob(str))));
                } catch (e) {
                    return null;
                }
            },
            function(str) {
                try {
                    return JSON.parse(str);
                } catch (e) {
                    return null;
                }
            },
            function(str) {
                try {
                    return JSON.parse(decodeURIComponent(str));
                } catch (e) {
                    return null;
                }
            }
        ];

        for (let i = 0; i < attempts.length; i++) {
            const parsed = attempts[i](encoded);
            if (parsed && typeof parsed === 'object') {
                return parsed;
            }
        }

        return null;
    }

    function formatCopyPayload(payload) {
        return [
            'Plugin version: ' + (payload.plugin_version || '—'),
            'WordPress version: ' + (payload.wordpress_version || '—'),
            'PHP version: ' + (payload.php_version || '—'),
            'Last API error: ' + (payload.last_api_error || '—'),
            'Last API request: ' + (payload.last_api_request || '—'),
            'Site URL: ' + (payload.site_url || '—')
        ].join('\n');
    }

    function copyTextToClipboard(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function(resolve, reject) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'absolute';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                const copied = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (copied) {
                    resolve();
                } else {
                    reject(new Error('copy_failed'));
                }
            } catch (err) {
                reject(err);
            }
        });
    }

    function openContextModal(button) {
        const encoded = button.getAttribute('data-context-data');
        if (!encoded) {
            return;
        }

        const context = decodeContext(encoded);
        const sections = buildModalSections(context);
        const level = button.getAttribute('data-log-level') || '';
        const message = button.getAttribute('data-log-message') || '';
        const timestamp = button.getAttribute('data-log-time') || '';
        const metaParts = [capitalize(level), timestamp, message].filter(Boolean);

        const $modal = $panel.find('[data-debug-modal]');
        if (!$modal.length) {
            return;
        }

        $panel.find('#bbai-debug-modal-title').text(strings.modalTitle || 'Log Context Details');
        $panel.find('[data-debug-modal-meta]').text(metaParts.join(' • ') || '—');
        $panel.find('[data-debug-modal-request]').text(sections.request);
        $panel.find('[data-debug-modal-response]').text(sections.response);
        $panel.find('[data-debug-modal-stack]').text(sections.stack);
        $modal.removeAttr('hidden');
    }

    function closeContextModal() {
        const $modal = $panel.find('[data-debug-modal]');
        if ($modal.length) {
            $modal.attr('hidden', 'hidden');
        }
    }

    function buildModalSections(context) {
        if (typeof context !== 'object' || context === null) {
            return {
                request: formatContext(context),
                response: strings.emptyResponse || 'No response payload was captured for this entry.',
                stack: strings.emptyStack || 'No stack trace was captured for this entry.',
            };
        }

        const request = pickContextSection(context, [
            'request_payload',
            'request',
            'payload',
            'body',
            'image',
            'image_data',
            'headers',
            'body_preview'
        ]);
        const requestFallback = pickContextSummary(context, [
            'endpoint',
            'method',
            'url',
            'api_host',
            'api_path',
            'payload_keys',
            'image_id',
            'regenerate',
            'has_body',
            'body_size',
            'body_preview'
        ]);
        const requestPayload = request || requestFallback || context;

        const response = pickContextSection(context, [
            'response_payload',
            'response',
            'api_response',
            'data',
            'backend_error'
        ]);
        const responseFallback = pickContextSummary(context, [
            'status',
            'status_code',
            'success',
            'data_keys',
            'error_message',
            'error_code',
            'backend_error',
            'backend_code'
        ]);
        const responsePayload = response || responseFallback || context;

        const stack = pickContextSection(context, [
            'stack_trace',
            'stacktrace',
            'trace',
            'stack',
            'exception'
        ]) || (context.error && typeof context.error === 'object' && context.error.stack ? context.error.stack : null);

        return {
            request: requestPayload ? formatContext(requestPayload) : (strings.emptyPayload || 'No request payload was captured for this entry.'),
            response: responsePayload ? formatContext(responsePayload) : (strings.emptyResponse || 'No response payload was captured for this entry.'),
            stack: stack ? formatContext(stack) : (strings.emptyStack || 'No stack trace was captured for this entry.'),
        };
    }

    function pickContextSection(context, keys) {
        for (let i = 0; i < keys.length; i++) {
            const key = keys[i];
            if (Object.prototype.hasOwnProperty.call(context, key) && context[key] !== null && typeof context[key] !== 'undefined' && context[key] !== '') {
                return context[key];
            }
        }
        return null;
    }

    function pickContextSummary(context, keys) {
        const summary = {};
        keys.forEach(function(key) {
            if (Object.prototype.hasOwnProperty.call(context, key) && context[key] !== null && typeof context[key] !== 'undefined' && context[key] !== '') {
                summary[key] = context[key];
            }
        });
        return Object.keys(summary).length ? summary : null;
    }

    function decodeContext(encoded) {
        const attempts = [
            function(str) {
                try {
                    if (/^[A-Za-z0-9+\/]*={0,2}$/.test(str)) {
                        return JSON.parse(decodeURIComponent(escape(atob(str))));
                    }
                } catch (e) {}
                return null;
            },
            function(str) {
                try {
                    return JSON.parse(str);
                } catch (e) {
                    return null;
                }
            },
            function(str) {
                try {
                    return JSON.parse(decodeURIComponent(str));
                } catch (e) {
                    return null;
                }
            },
            function(str) {
                try {
                    return decodeURIComponent(escape(atob(str)));
                } catch (e) {
                    return null;
                }
            }
        ];

        for (let i = 0; i < attempts.length; i++) {
            const result = attempts[i](encoded);
            if (result !== null) {
                return result;
            }
        }

        return {
            _error: 'Unable to decode context',
            _raw: String(encoded).substring(0, 120) + '...',
            _note: 'The context data could not be decoded.'
        };
    }

    function formatContext(context) {
        if (typeof context === 'object' && context !== null) {
            try {
                return JSON.stringify(context, null, 2);
            } catch (err) {
                return String(context);
            }
        }

        if (typeof context === 'string') {
            return context;
        }

        return context === null || typeof context === 'undefined'
            ? (strings.emptyContext || 'No additional context was provided for this entry.')
            : String(context);
    }

    function encodeContext(context) {
        const raw = typeof context === 'string' ? context : JSON.stringify(context);
        try {
            return btoa(unescape(encodeURIComponent(raw)));
        } catch (e) {
            return encodeURIComponent(raw);
        }
    }

    function encodeCopyPayload(payload) {
        try {
            return btoa(unescape(encodeURIComponent(JSON.stringify(payload))));
        } catch (e) {
            return '';
        }
    }

    function getBadgeVariant(level) {
        if (level === 'warning') {
            return 'warning';
        }
        if (level === 'error') {
            return 'error';
        }
        if (level === 'debug') {
            return 'pending';
        }
        return 'info';
    }

    function addNonce(xhr) {
        if (config.nonce) {
            xhr.setRequestHeader('X-WP-Nonce', config.nonce);
        }
    }

    function buildRestRouteFallback(url, extraQueryData) {
        if (typeof url !== 'string' || !url) {
            return '';
        }

        const marker = '/wp-json/';
        const markerIndex = url.indexOf(marker);
        if (markerIndex === -1) {
            return '';
        }

        const base = url.slice(0, markerIndex + 1);
        const remainder = url.slice(markerIndex + marker.length);
        const parts = remainder.split('?');
        const routePart = (parts[0] || '').replace(/^\/+/, '');

        if (!routePart) {
            return '';
        }

        const params = new URLSearchParams(parts[1] || '');
        params.set('rest_route', '/' + routePart);

        if (extraQueryData && typeof extraQueryData === 'object') {
            Object.keys(extraQueryData).forEach(function(key) {
                const value = extraQueryData[key];
                params.set(key, value === null || typeof value === 'undefined' ? '' : String(value));
            });
        }

        return base + '?' + params.toString();
    }

    function ajaxWithRestFallback(options) {
        const dfd = $.Deferred();
        const method = String(options && (options.method || options.type) || 'GET').toUpperCase();
        const queryData = method === 'GET' ? (options.data || null) : null;
        const fallbackUrl = buildRestRouteFallback(options.url, queryData);
        const requestOptions = $.extend({}, options);

        if (state.useRestRouteFallback && fallbackUrl) {
            requestOptions.url = fallbackUrl;
            if (method === 'GET') {
                requestOptions.data = undefined;
            }
        }

        $.ajax(requestOptions)
            .done(function(response, textStatus, xhr) {
                if (requestOptions.url === fallbackUrl && fallbackUrl) {
                    state.useRestRouteFallback = true;
                }
                dfd.resolve(response, textStatus, xhr);
            })
            .fail(function(xhr, status, error) {
                if (
                    xhr &&
                    xhr.status === 404 &&
                    fallbackUrl &&
                    requestOptions.url !== fallbackUrl
                ) {
                    const retryOptions = $.extend({}, options, { url: fallbackUrl });
                    if (method === 'GET') {
                        retryOptions.data = undefined;
                    }

                    state.useRestRouteFallback = true;

                    $.ajax(retryOptions)
                        .done(function(response, textStatus, xhr2) {
                            dfd.resolve(response, textStatus, xhr2);
                        })
                        .fail(function(xhr2, status2, error2) {
                            dfd.reject(xhr2, status2, error2);
                        });
                    return;
                }

                dfd.reject(xhr, status, error);
            });

        return dfd.promise();
    }

    function fetchLogs() {
        toggleLoading(true);
        return ajaxWithRestFallback({
            url: config.restLogs,
            method: 'GET',
            data: {
                page: state.page,
                per_page: state.perPage,
                level: state.level,
                date_from: state.dateFrom,
                date_to: state.dateTo,
                search: state.search,
            },
            beforeSend: addNonce,
        })
        .done(function(response) {
            render(response);
        })
        .fail(function() {
            showToast(strings.errorGeneric, 'error');
        })
        .always(function() {
            toggleLoading(false);
        });
    }

    function render(payload) {
        if (!payload) {
            return;
        }
        const pagination = payload.pagination || {};
        const stats = payload.stats || {};

        state.page = pagination.page || state.page;
        state.perPage = pagination.per_page || state.perPage;

        renderStats(stats);
        renderServiceStatus(payload.service_status || {});
        renderRecentErrors(payload.recent_errors || []);
        renderRows(payload.logs || []);
        renderPagination(pagination);
        updateCopyPayload(payload.copy_debug_info || null);
        $panel.data('debug-pagination', pagination);
    }

    function renderStats(stats) {
        setStat('total', stats.total || 0);
        setStat('warnings', stats.warnings || 0);
        setStat('errors', stats.errors || 0);
        $panel.find('[data-debug-stat="last_api"]').text(stats.last_event || stats.last_api || '—');
    }

    function renderServiceStatus(serviceStatus) {
        const status = String(serviceStatus.connection_status || 'failed').toLowerCase();
        const isConnected = status === 'connected';
        const $connection = $panel.find('[data-debug-service="connection"]');
        if ($connection.length) {
            $connection
                .text(isConnected ? strings.connected : strings.failed)
                .removeClass('bbai-status-badge--success bbai-status-badge--error')
                .addClass(isConnected ? 'bbai-status-badge--success' : 'bbai-status-badge--error');
        }

        $panel.find('[data-debug-service="last_request"]').text(serviceStatus.last_api_request || '—');

        const avgMs = parseInt(serviceStatus.average_response_time_ms, 10);
        $panel.find('[data-debug-service="avg_response"]').text(Number.isFinite(avgMs) && avgMs > 0 ? (avgMs + ' ms') : '—');
    }

    function renderRecentErrors(errors) {
        const $section = $panel.find('[data-debug-recent-errors-section]');
        const $tbody = $panel.find('[data-debug-recent-errors]');
        if (!$section.length || !$tbody.length) {
            return;
        }

        if (!Array.isArray(errors) || !errors.length) {
            $section.attr('hidden', 'hidden');
            $tbody.html('<tr><td colspan="4" class="bbai-text-center bbai-text-muted bbai-text-sm">' + escapeHtml(strings.noWarnings) + '</td></tr>');
            return;
        }

        $section.removeAttr('hidden');
        const rows = errors.slice(0, 5).map(function(item) {
            const level = String(item.level || 'warning').toLowerCase();
            const badge = level === 'error' ? 'bbai-status-badge--error' : 'bbai-status-badge--warning';
            const rowClass = level === 'error' ? 'bbai-debug-recent-errors__row--error' : '';
            const contextText = summarizeContext(item.context);

            return '<tr class="' + escapeAttr(rowClass) + '">' +
                '<td class="bbai-text-muted">' + escapeHtml(item.created_at || '') + '</td>' +
                '<td><span class="bbai-status-badge ' + escapeAttr(badge) + '">' + escapeHtml(capitalize(level)) + '</span></td>' +
                '<td>' + escapeHtml(item.message || '') + '</td>' +
                '<td class="bbai-debug-recent-errors__context">' + escapeHtml(contextText) + '</td>' +
            '</tr>';
        }).join('');

        $tbody.html(rows);
    }

    function summarizeContext(context) {
        let value = '—';
        if (typeof context === 'string' && context) {
            value = context;
        } else if (context && typeof context === 'object') {
            try {
                value = JSON.stringify(context);
            } catch (e) {
                value = '[object]';
            }
        }

        if (value.length > 180) {
            return value.slice(0, 180) + '...';
        }

        return value;
    }

    function setStat(target, value) {
        const formatted = new Intl.NumberFormat().format(value);
        $panel.find('[data-debug-stat="' + target + '"]').text(formatted);
    }

    function renderRows(logs) {
        const $tbody = $panel.find('[data-debug-rows]');
        if (!logs.length) {
            $tbody.html('<tr><td colspan="4" class="bbai-text-center bbai-text-muted bbai-text-sm">' + escapeHtml(strings.noLogs) + '</td></tr>');
            return;
        }

        const rows = logs.map(function(log) {
            const level = (log.level || 'info').toLowerCase();
            const badgeVariant = getBadgeVariant(level);
            const badge = '<span class="bbai-status-badge bbai-status-badge--' + badgeVariant + '">' + escapeHtml(capitalize(level)) + '</span>';

            let contextCell = '<span class="bbai-text-muted">—</span>';
            const hasContext = log.context && (typeof log.context === 'object' ? Object.keys(log.context).length : String(log.context).trim() !== '');
            if (hasContext) {
                const encoded = encodeContext(log.context);
                contextCell = '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-debug-context data-context-data="' + escapeAttr(encoded) + '" data-log-level="' + escapeAttr(level) + '" data-log-time="' + escapeAttr(log.created_at || '') + '" data-log-message="' + escapeAttr(log.message || '') + '">' + escapeHtml(strings.contextTitle) + '</button>';
            }

            return '<tr>' +
                '<td class="bbai-text-muted">' + escapeHtml(log.created_at || '') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td>' + escapeHtml(log.message || '') + '</td>' +
                '<td>' + contextCell + '</td>' +
                '</tr>';
        }).join('');

        $tbody.html(rows);
    }

    function updateCopyPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }
        const encoded = encodeCopyPayload(payload);
        if (!encoded) {
            return;
        }
        $panel.find('[data-debug-copy-info]').attr('data-copy-debug', encoded);
    }

    function renderPagination(pagination) {
        const totalPages = Math.max(1, pagination.total_pages || 1);
        const currentPage = pagination.page || 1;
        const $prev = $panel.find('[data-debug-page="prev"]');
        const $next = $panel.find('[data-debug-page="next"]');
        $prev.prop('disabled', currentPage <= 1).toggleClass('bbai-pagination-btn--disabled', currentPage <= 1);
        $next.prop('disabled', currentPage >= totalPages).toggleClass('bbai-pagination-btn--disabled', currentPage >= totalPages);
        const text = strings.pageIndicator
            .replace('%1$d', currentPage)
            .replace('%2$d', totalPages);
        $panel.find('[data-debug-page-indicator]').text(text);
    }

    function toggleLoading(isLoading) {
        $panel.toggleClass('is-loading', !!isLoading);
    }

    function showToast(message, type) {
        const $toast = $panel.find('[data-debug-toast]');
        if (!$toast.length) {
            return;
        }
        $toast.text(message || '');
        $toast.removeClass('is-error is-success');
        if (type === 'error') {
            $toast.addClass('is-error');
        } else {
            $toast.addClass('is-success');
        }
        $toast.removeAttr('hidden');
        setTimeout(function() {
            $toast.attr('hidden', 'hidden');
        }, 4000);
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') {
            return '';
        }
        return str.replace(/[&<>"']/g, function(match) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#039;'
            })[match];
        });
    }

    function escapeAttr(str) {
        if (str === null || typeof str === 'undefined') {
            return '';
        }
        return String(str).replace(/[&<>"']/g, function(match) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#039;'
            })[match];
        });
    }

    function capitalize(str) {
        if (!str) {
            return '';
        }
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    init();
})(jQuery);
