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
        date: '',
        search: '',
    };

    function init() {
        // Ensure modal is hidden on load
        hideContext();
        
        // Bind events first so they're ready for any dynamic content
        bindEvents();
        
        if (config.initial) {
            render(config.initial);
        }
        
        // Also add vanilla JS fallback for context buttons
        addVanillaContextHandlers();
    }
    
    function addVanillaContextHandlers() {
        // Vanilla JS handler for context buttons - works independently of jQuery
        document.addEventListener('click', function(e) {
            const button = e.target.closest('[data-debug-context-trigger]');
            if (!button) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            console.log('[AltText AI Debug] Context button clicked');
            
            const encoded = button.getAttribute('data-debug-context-trigger');
            const rowId = button.getAttribute('data-row-id');
            
            if (!encoded || !rowId) {
                console.warn('[AltText AI Debug] Missing context data:', { encoded: !!encoded, rowId: !!rowId });
                return;
            }
            
            const panel = document.querySelector('[data-bbai-debug-panel]');
            if (!panel) {
                console.warn('[AltText AI Debug] Panel not found');
                return;
            }
            
            // Find the context row
                const contextRow = panel.querySelector('[data-context-row-for="' + rowId + '"]');
                const contextBody = contextRow ? contextRow.querySelector('[data-debug-context-body="' + rowId + '"]') : null;
                
                if (!contextRow || !contextBody) {
                console.warn('[AltText AI Debug] Context elements not found:', { 
                    contextRow: !!contextRow, 
                    contextBody: !!contextBody, 
                    rowId: rowId,
                    allRows: panel.querySelectorAll('[data-context-row-for]').length
                });
                    return;
                }
                
            console.log('[AltText AI Debug] Found context elements, processing...');
            
            // Check if expanded
            const isExpanded = contextRow.style.display !== 'none' && contextRow.offsetParent !== null;
                
                if (isExpanded) {
                // Collapse
                    contextRow.style.display = 'none';
                    button.classList.remove('is-expanded');
                    button.textContent = strings.contextTitle || 'Log Context';
                } else {
                // Expand - decode context
                    let context = null;
                
                // Try decoding strategies
                const strategies = [
                    (str) => {
                    try {
                            if (/^[A-Za-z0-9+\/]*={0,2}$/.test(str)) {
                                return JSON.parse(decodeURIComponent(escape(atob(str))));
                            }
                        } catch(e) {}
                        return null;
                    },
                    (str) => {
                        try { return JSON.parse(str); } catch(e) { return null; }
                    },
                    (str) => {
                        try { return JSON.parse(decodeURIComponent(str)); } catch(e) { return null; }
                    },
                    (str) => {
                        try { return JSON.parse(decodeURIComponent(decodeURIComponent(str))); } catch(e) { return null; }
                    }
                ];
                
                for (let strategy of strategies) {
                    context = strategy(encoded);
                    if (context !== null) break;
                }
                
                if (context === null) {
                    context = {
                        _error: 'Unable to decode context',
                        _raw: encoded.substring(0, 100) + '...',
                        _note: 'The context data could not be decoded.'
                    };
                            }
                
                // Format output
                let output = '';
                if (typeof context === 'object' && context !== null) {
                    try {
                        output = JSON.stringify(context, null, 2);
                    } catch (err) {
                        output = 'Context Object:\n' + Object.keys(context).map(key => {
                            return '  ' + key + ': ' + (typeof context[key] === 'object' ? JSON.stringify(context[key], null, 2) : String(context[key]));
                        }).join('\n');
                    }
                } else {
                    output = String(context);
                }
                
                // Display
                    contextBody.textContent = output;
                    contextRow.style.display = 'table-row';
                    button.classList.add('is-expanded');
                    button.textContent = strings.contextHide || 'Hide Context';
                }
        }, true);
        
    }

    function bindEvents() {
        $panel.on('submit', '[data-debug-filter]', function(event) {
            event.preventDefault();
            const $form = $(this);
            state.level = $form.find('[name="level"]').val() || '';
            state.date = $form.find('[name="date"]').val() || '';
            state.search = $form.find('[name="search"]').val() || '';
            state.page = 1;
            fetchLogs();
        });

        $panel.on('click', '[data-debug-reset]', function() {
            const $form = $panel.find('[data-debug-filter]');
            state.level = '';
            state.date = '';
            state.search = '';
            state.page = 1;
            if ($form.length && $form[0].reset) {
                $form[0].reset();
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

        $panel.on('click', '[data-debug-context-trigger]', function(e) {
            // Check if vanilla handler already processed this (it runs first with capture: true)
            if (e.defaultPrevented || this.classList.contains('is-expanded') && this.textContent.includes('Hide')) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const encoded = $button.attr('data-debug-context-trigger');
            const rowId = $button.attr('data-row-id');
            
            if (!encoded || !rowId) {
                console.warn('[AltText AI Debug] Missing context data:', { encoded: !!encoded, rowId: !!rowId });
                return;
            }
            
            // Find the context row for this log entry
            const $contextRow = $panel.find('[data-context-row-for="' + rowId + '"]');
            const $contextBody = $contextRow.find('[data-debug-context-body="' + rowId + '"]');
            
            if ($contextRow.length === 0) {
                console.warn('[AltText AI Debug] Context row not found for row ID:', rowId);
                return;
            }
            
            if ($contextBody.length === 0) {
                console.warn('[AltText AI Debug] Context body element not found for row ID:', rowId);
                return;
            }
            
            // Check if already expanded by checking display style
            const isExpanded = $contextRow.is(':visible') && $contextRow.css('display') !== 'none';
            
            if (isExpanded) {
                // Collapse
                $contextRow.slideUp(200, function() {
                    $contextRow.css('display', 'none');
                });
                $button.removeClass('is-expanded');
                $button.text(strings.contextTitle || 'Log Context');
            } else {
                // Expand - decode and show context
                let context = null;
                
                // Try multiple decoding strategies
                const decodeAttempts = [
                    // Strategy 1: Base64 decode (new method - more reliable)
                    function(str) {
                        try {
                            if (/^[A-Za-z0-9+\/]*={0,2}$/.test(str)) {
                                const decoded = decodeURIComponent(escape(atob(str)));
                                return JSON.parse(decoded);
                            }
                            return null;
                        } catch (e) {
                            return null;
                        }
                    },
                    // Strategy 2: Try parsing directly
                    function(str) {
                        try {
                            return JSON.parse(str);
                        } catch (e) {
                            return null;
                        }
                    },
                    // Strategy 3: URL decode once
                    function(str) {
                        try {
                            return JSON.parse(decodeURIComponent(str));
                        } catch (e) {
                            return null;
                        }
                    },
                    // Strategy 4: URL decode twice
                    function(str) {
                        try {
                            return JSON.parse(decodeURIComponent(decodeURIComponent(str)));
                        } catch (e) {
                            return null;
                        }
                    }
                ];
                
                // Try each decoding strategy
                for (let i = 0; i < decodeAttempts.length; i++) {
                    const result = decodeAttempts[i](encoded);
                    if (result !== null) {
                        context = result;
                        break;
                    }
                }
                
                // If all strategies failed, show error
                if (context === null) {
                    context = {
                        _error: 'Unable to decode context',
                        _raw: encoded.substring(0, 100) + '...',
                        _note: 'The context data could not be decoded. This may be a formatting issue.'
                    };
                }
                
                // Format and display context
                let output = '';
                if (context) {
                    if (typeof context === 'object' && context !== null) {
                        try {
                            output = JSON.stringify(context, null, 2);
                        } catch (err) {
                            output = 'Context Object:\n' + Object.keys(context).map(function(key) {
                                return '  ' + key + ': ' + (typeof context[key] === 'object' ? JSON.stringify(context[key], null, 2) : String(context[key]));
                            }).join('\n');
                        }
                    } else if (typeof context === 'string') {
                        try {
                            const parsed = JSON.parse(context);
                            output = JSON.stringify(parsed, null, 2);
                        } catch (err) {
                            output = context;
                        }
                    } else {
                        output = String(context);
                    }
                }
                
                // Set the content
                $contextBody.text(output);
                
                // Show the row
                $contextRow.css('display', 'table-row');
                $contextRow.slideDown(200);
                $button.addClass('is-expanded');
                $button.text(strings.contextHide || 'Hide Context');
            }
        });

        $panel.on('click', '[data-debug-context-close]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideContext();
        });
        
        // Handle copy button
        $panel.on('click', '[data-debug-context-copy]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const $body = $panel.find('[data-debug-context-body]');
            const text = $body.text();
            
            if (text && text !== strings.emptyContext) {
                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        const originalText = $btn.text();
                        $btn.text('Copied!').css('background-color', '#10b981').css('color', 'white');
                        setTimeout(function() {
                            $btn.text(originalText).css('background-color', '').css('color', '');
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Failed to copy:', err);
                        window.bbaiModal.warning('Failed to copy to clipboard. Please select and copy manually.');
                    });
                } else {
                    // Fallback for older browsers
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        const originalText = $btn.text();
                        $btn.text('Copied!').css('background-color', '#10b981').css('color', 'white');
                        setTimeout(function() {
                            $btn.text(originalText).css('background-color', '').css('color', '');
                        }, 2000);
                } catch (err) {
                        window.bbaiModal.warning('Failed to copy to clipboard. Please select and copy manually.');
                }
                    document.body.removeChild(textarea);
                }
            }
        });

        // Also bind directly to the × button as fallback
        $panel.on('click', '.bbai-debug-context__close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideContext();
        });
        
        $panel.on('click', '[data-debug-context-panel]', function(event) {
            if (event.target === this) {
                hideContext();
            }
        });
        
        // Also bind to .bbai-debug-context directly
        $panel.on('click', '.bbai-debug-context', function(event) {
            if (event.target === this) {
                hideContext();
            }
        });
        
        $(document).on('keydown', function(event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                hideContext();
            }
        });
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
                date: state.date,
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
        $panel.find('[data-debug-stat="last_api"]').text(stats.last_api || '—');
    }

    function setStat(target, value) {
        const formatted = new Intl.NumberFormat().format(value);
        $panel.find('[data-debug-stat="' + target + '"]').text(formatted);
    }

    function renderRows(logs) {
        const $tbody = $panel.find('[data-debug-rows]');
        if (!logs.length) {
            $tbody.html('<tr class="bbai-debug-table__empty"><td colspan="4" style="text-align: center; padding: 60px 24px; color: #6b7280; font-size: 14px;">' + escapeHtml(strings.noLogs) + '</td></tr>');
            return;
        }

        // Badge styles matching PHP
        const badgeStyles = {
            'info': 'background: #eff6ff; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; display: inline-block;',
            'warning': 'background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; display: inline-block;',
            'error': 'background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; display: inline-block;',
            'debug': 'background: #f3f4f6; color: #374151; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; display: inline-block;'
        };

        const rows = logs.map(function(log, index) {
            const level = (log.level || 'info').toLowerCase();
            const badgeStyle = badgeStyles[level] || badgeStyles.info;
            const badge = '<span style="' + badgeStyle + '">' + escapeHtml(capitalize(level)) + '</span>';
            
            // Context column
            let contextCell = '<span style="color: #9ca3af; font-size: 13px; font-style: italic;">—</span>';
            if (log.context && Object.keys(log.context).length) {
                // Use base64 encoding
                try {
                    const json = JSON.stringify(log.context);
                    const encoded = btoa(unescape(encodeURIComponent(json)));
                    const rowIndex = String(index);
                    contextCell = '<button type="button" class="bbai-debug-context-btn" data-context-data="' + escapeAttr(encoded) + '" data-row-index="' + escapeAttr(rowIndex) + '" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; cursor: pointer;">' + escapeHtml(strings.contextTitle) + '</button>';
                } catch (e) {
                    // Fallback to URL encoding if base64 fails
                    try {
                const encoded = encodeURIComponent(JSON.stringify(log.context));
                        const rowIndex = String(index);
                        contextCell = '<button type="button" class="bbai-debug-context-btn" data-context-data="' + escapeAttr(encoded) + '" data-row-index="' + escapeAttr(rowIndex) + '" style="padding: 6px 12px; border-radius: 6px; font-size: 13px; cursor: pointer;">' + escapeHtml(strings.contextTitle) + '</button>';
                    } catch (e2) {
                        contextCell = '<span style="color: #9ca3af; font-size: 13px; font-style: italic;">—</span>';
                    }
                }
            }
            
            const rowIndex = String(index);
            const rowHtml = '<tr class="bbai-debug-table-row" style="border-bottom: 1px solid #f3f4f6; transition: background 0.15s;">' +
                '<td style="padding: 12px 16px; color: #6b7280; font-size: 13px;">' + escapeHtml(log.created_at || '') + '</td>' +
                '<td style="padding: 12px 16px;">' + badge + '</td>' +
                '<td style="padding: 12px 16px; color: #374151; font-size: 13px;">' + escapeHtml(log.message || '') + '</td>' +
                '<td style="padding: 12px 16px;">' + contextCell + '</td>' +
                '</tr>';
            
            // Also create context row if context exists
            if (log.context && Object.keys(log.context).length) {
                return rowHtml + '<tr class="bbai-debug-context-row" data-row-index="' + escapeAttr(rowIndex) + '" style="display: none;"><td colspan="4" class="bbai-debug-context-cell"><div class="bbai-debug-context-content"><pre class="bbai-debug-context-json"></pre></div></td></tr>';
            }
            
            return rowHtml;
        }).join('');

        $tbody.html(rows);
    }

    function renderPagination(pagination) {
        const totalPages = Math.max(1, pagination.total_pages || 1);
        const currentPage = pagination.page || 1;
        $panel.find('[data-debug-page="prev"]').prop('disabled', currentPage <= 1);
        $panel.find('[data-debug-page="next"]').prop('disabled', currentPage >= totalPages);
        const text = strings.pageIndicator
            .replace('%1$d', currentPage)
            .replace('%2$d', totalPages);
        $panel.find('[data-debug-page-indicator]').text(text);
    }

    function showContext(context) {
        const $overlay = $panel.find('[data-debug-context-panel]');
        const $body = $overlay.find('[data-debug-context-body]');
        let output = strings.emptyContext;
        
        if (context) {
            if (typeof context === 'object' && context !== null) {
                // Format JSON with proper indentation
                try {
            output = JSON.stringify(context, null, 2);
                } catch (err) {
                    // If stringify fails, try to show object properties
                    output = 'Context Object:\n' + Object.keys(context).map(function(key) {
                        return '  ' + key + ': ' + (typeof context[key] === 'object' ? JSON.stringify(context[key], null, 2) : String(context[key]));
                    }).join('\n');
                }
            } else if (typeof context === 'string') {
                // If it's a string, try to parse it as JSON first
                try {
                    const parsed = JSON.parse(context);
                    output = JSON.stringify(parsed, null, 2);
                } catch (err) {
                    // If not JSON, show as-is
                    output = context;
                }
            } else {
            output = String(context);
            }
        }
        
        // Set the formatted output
        $body.text(output);
        
        // Remove hidden attribute and show modal
        $overlay.removeAttr('hidden');
        $overlay.css('display', 'flex');
        
        // Prevent body scroll when modal is open
        $('body').css('overflow', 'hidden');
    }

    function hideContext() {
        var $modal = $panel.find('[data-debug-context-panel]');
        $modal.attr('hidden', 'hidden');
        $modal.css('display', 'none');
        
        // Also use jQuery hide as fallback
        var $modalDirect = $panel.find('.bbai-debug-context');
        $modalDirect.attr('hidden', 'hidden').hide();
        
        // Restore body scroll
        $('body').css('overflow', '');
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
        if (!str) {
            return '';
        }
        return String(str).replace(/[^a-z0-9_-]/gi, '');
    }

    function capitalize(str) {
        if (!str) {
            return '';
        }
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    init();
})(jQuery);
