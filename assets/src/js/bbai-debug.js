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
        contextTitle: 'Log Context',
        contextHide: 'Hide Context',
        clearConfirm: 'This will permanently delete all debug logs. Continue?',
        errorGeneric: 'Unable to load debug logs. Please try again.',
        emptyContext: 'No additional context was provided for this entry.',
        cleared: 'Logs cleared successfully.',
        pageIndicator: 'Page %1$d of %2$d',
    }, config.strings || {});

    const state = {
        page: (config.initial && config.initial.pagination && config.initial.pagination.page) || 1,
        perPage: (config.initial && config.initial.pagination && config.initial.pagination.per_page) || 10,
        level: '',
        dateFrom: '',
        dateTo: '',
        search: '',
    };

    function init() {
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
            $.ajax({
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
            toggleContextRow(this);
        });
    }

    function syncFilters() {
        const $form = $panel.find('[data-debug-filter]');
        state.level = $form.find('[name="level"]').val() || '';
        state.dateFrom = $form.find('[name="date_from"]').val() || '';
        state.dateTo = $form.find('[name="date_to"]').val() || '';
        state.search = $form.find('[name="search"]').val() || '';
    }

    function toggleContextRow(button) {
        const encoded = button.getAttribute('data-context-data');
        const rowIndex = button.getAttribute('data-row-index');

        if (!encoded || !rowIndex) {
            return;
        }

        const $contextRow = $panel.find('.bbai-debug-context-row[data-row-index="' + rowIndex + '"]');
        if (!$contextRow.length) {
            return;
        }

        const preElement = $contextRow.find('pre')[0];
        if (!preElement) {
            return;
        }

        const isExpanded = $contextRow.hasClass('is-expanded');
        if (isExpanded) {
            $contextRow.removeClass('is-expanded');
            button.setAttribute('aria-expanded', 'false');
            button.textContent = strings.contextTitle || 'Log Context';
            button.classList.remove('is-expanded');
            return;
        }

        const context = decodeContext(encoded);
        preElement.textContent = formatContext(context);
        $contextRow.addClass('is-expanded');
        button.setAttribute('aria-expanded', 'true');
        button.textContent = strings.contextHide || 'Hide Context';
        button.classList.add('is-expanded');
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
                    return JSON.parse(decodeURIComponent(decodeURIComponent(str)));
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
            _raw: String(encoded).substring(0, 100) + '...',
            _note: 'The context data could not be decoded.'
        };
    }

    function formatContext(context) {
        if (typeof context === 'object' && context !== null) {
            try {
                return JSON.stringify(context, null, 2);
            } catch (err) {
                return 'Context Object:\n' + Object.keys(context).map(function(key) {
                    return '  ' + key + ': ' + (typeof context[key] === 'object' ? JSON.stringify(context[key], null, 2) : String(context[key]));
                }).join('\n');
            }
        }

        if (typeof context === 'string') {
            return context;
        }

        return String(context);
    }

    function encodeContext(context) {
        const raw = typeof context === 'string' ? context : JSON.stringify(context);
        try {
            return btoa(unescape(encodeURIComponent(raw)));
        } catch (e) {
            return encodeURIComponent(raw);
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

    function fetchLogs() {
        toggleLoading(true);
        return $.ajax({
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
        renderRows(payload.logs || []);
        renderPagination(pagination);
        $panel.data('debug-pagination', pagination);
    }

    function renderStats(stats) {
        setStat('total', stats.total || 0);
        setStat('warnings', stats.warnings || 0);
        setStat('errors', stats.errors || 0);
        $panel.find('[data-debug-stat="last_api"]').text(stats.last_event || stats.last_api || '—');
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

        const rows = logs.map(function(log, index) {
            const rowIndex = String(index + 1);
            const level = (log.level || 'info').toLowerCase();
            const badgeVariant = getBadgeVariant(level);
            const badge = '<span class="bbai-status-badge bbai-status-badge--' + badgeVariant + '">' + escapeHtml(capitalize(level)) + '</span>';
            
            // Context column
            let contextCell = '<span class="bbai-text-muted">—</span>';
            let contextRow = '';
            const hasContext = log.context && (typeof log.context === 'object' ? Object.keys(log.context).length : String(log.context).trim() !== '');
            if (hasContext) {
                const encoded = encodeContext(log.context);
                contextCell = '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-debug-context data-context-data="' + escapeAttr(encoded) + '" data-row-index="' + escapeAttr(rowIndex) + '" aria-expanded="false">' + escapeHtml(strings.contextTitle) + '</button>';
                contextRow = '<tr class="bbai-debug-context-row" data-row-index="' + escapeAttr(rowIndex) + '">' +
                    '<td colspan="4" class="bbai-p-2">' +
                        '<div class="bbai-card bbai-card--compact">' +
                            '<pre class="bbai-text-sm" style="font-family: var(--bbai-font-mono); white-space: pre-wrap; word-break: break-word; margin: 0;"></pre>' +
                        '</div>' +
                    '</td>' +
                '</tr>';
            }

            const rowHtml = '<tr data-row-index="' + escapeAttr(rowIndex) + '">' +
                '<td class="bbai-text-muted">' + escapeHtml(log.created_at || '') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td>' + escapeHtml(log.message || '') + '</td>' +
                '<td>' + contextCell + '</td>' +
                '</tr>';

            return rowHtml + contextRow;
        }).join('');

        $tbody.html(rows);
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
                "'": '&#039;'
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
                "'": '&#039;'
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
