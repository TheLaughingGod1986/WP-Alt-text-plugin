(function(window, document) {
    'use strict';

    var DEFAULT_DURATION = 4000;
    var CONTAINER_SELECTOR = '.bbai-toast-container';
    var icons = {
        success: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"></circle><path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
        error: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"></circle><path d="M10 6V10M10 14H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>',
        warning: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2L18 16H2L10 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path><path d="M10 8V12M10 15H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>',
        info: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"></circle><path d="M10 8V12M10 6H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>'
    };

    function ensureContainer() {
        var container = document.querySelector(CONTAINER_SELECTOR);
        if (container) {
            return container;
        }

        if (!document.body) {
            return null;
        }

        container = document.createElement('div');
        container.className = 'bbai-toast-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'false');
        document.body.appendChild(container);
        return container;
    }

    function escapeHtml(text) {
        var node = document.createElement('div');
        node.textContent = text == null ? '' : String(text);
        return node.innerHTML;
    }

    function normalizeAction(action, options) {
        var legacyOptions = options && typeof options === 'object' ? options : {};
        if (action && typeof action === 'object') {
            return action;
        }

        if (typeof action === 'function') {
            return {
                label: legacyOptions.actionLabel || legacyOptions.label || '',
                onClick: action
            };
        }

        if (legacyOptions.action || legacyOptions.actionLabel || legacyOptions.url) {
            return {
                label: legacyOptions.actionLabel || legacyOptions.label || '',
                onClick: typeof legacyOptions.action === 'function' ? legacyOptions.action : null,
                url: legacyOptions.url || ''
            };
        }

        return null;
    }

    function normalizeConfig(firstArg, secondArg, thirdArg) {
        if (firstArg && typeof firstArg === 'object' && !Array.isArray(firstArg)) {
            return firstArg;
        }

        if (typeof secondArg === 'string' && (!thirdArg || typeof thirdArg !== 'object')) {
            return {
                message: firstArg,
                type: secondArg
            };
        }

        return {
            type: firstArg,
            message: secondArg,
            options: thirdArg
        };
    }

    function buildToastMarkup(config) {
        var title = config.title ? '<div class="bbai-toast-title">' + escapeHtml(config.title) + '</div>' : '';
        var message = config.message ? '<div class="bbai-toast-message">' + escapeHtml(config.message) + '</div>' : '';
        var action = '';

        if (config.action && config.action.label) {
            if (config.action.url) {
                action = '<a class="bbai-toast-action" href="' + escapeHtml(config.action.url) + '">' + escapeHtml(config.action.label) + '</a>';
            } else {
                action = '<button type="button" class="bbai-toast-action">' + escapeHtml(config.action.label) + '</button>';
            }
        }

        return '' +
            '<div class="bbai-toast-icon">' + (icons[config.type] || icons.info) + '</div>' +
            '<div class="bbai-toast-content">' + title + message + action + '</div>' +
            '<button type="button" class="bbai-toast-close" aria-label="Dismiss notification">' +
                '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                    '<path d="M12 4L4 12M4 4l8 8"></path>' +
                '</svg>' +
            '</button>';
    }

    function hideToast(toast) {
        if (!toast || toast.getAttribute('data-bbai-toast-hiding') === '1') {
            return;
        }

        toast.setAttribute('data-bbai-toast-hiding', '1');
        toast.classList.remove('show');
        toast.classList.add('hide');

        window.setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 320);
    }

    function showToast(firstArg, secondArg, thirdArg) {
        var normalized = normalizeConfig(firstArg, secondArg, thirdArg);
        var options = normalized.options && typeof normalized.options === 'object' ? normalized.options : {};
        var toastConfig = {
            type: String(normalized.type || options.type || 'info').toLowerCase(),
            title: normalized.title || options.title || '',
            message: normalized.message || options.message || '',
            duration: typeof normalized.duration === 'number' ? normalized.duration : (typeof options.duration === 'number' ? options.duration : DEFAULT_DURATION),
            dismissible: normalized.dismissible !== false && options.dismissible !== false,
            action: normalizeAction(normalized.action || options.action, options)
        };

        if (!toastConfig.message && !toastConfig.title) {
            return null;
        }

        var container = ensureContainer();
        if (!container) {
            return null;
        }

        var toast = document.createElement('div');
        toast.className = 'bbai-toast bbai-toast--' + toastConfig.type;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = buildToastMarkup(toastConfig);
        container.appendChild(toast);

        var closeButton = toast.querySelector('.bbai-toast-close');
        if (closeButton && !toastConfig.dismissible) {
            closeButton.hidden = true;
        }
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                hideToast(toast);
            });
        }

        var actionNode = toast.querySelector('.bbai-toast-action');
        if (actionNode && toastConfig.action && typeof toastConfig.action.onClick === 'function') {
            actionNode.addEventListener('click', function(event) {
                event.preventDefault();
                toastConfig.action.onClick(event);
                hideToast(toast);
            });
        } else if (actionNode && toastConfig.action && toastConfig.action.url) {
            actionNode.addEventListener('click', function() {
                hideToast(toast);
            });
        }

        window.requestAnimationFrame(function() {
            toast.classList.add('show');
        });

        if (toastConfig.duration > 0) {
            window.setTimeout(function() {
                hideToast(toast);
            }, toastConfig.duration);
        }

        return toast;
    }

    function legacyToast(type, message, options) {
        var normalizedOptions = options && typeof options === 'object' ? options : {};
        return showToast({
            type: type,
            title: normalizedOptions.title || '',
            message: message,
            duration: normalizedOptions.duration,
            dismissible: normalizedOptions.dismissible,
            action: normalizeAction(normalizedOptions.action, normalizedOptions)
        });
    }

    window.showToast = showToast;
    window.bbaiPushToast = legacyToast;
    window.bbaiToast = {
        show: function(message, options) {
            var normalizedOptions = options && typeof options === 'object' ? options : {};
            return showToast({
                type: normalizedOptions.type || 'info',
                title: normalizedOptions.title || '',
                message: message,
                duration: normalizedOptions.duration,
                dismissible: normalizedOptions.dismissible,
                action: normalizeAction(normalizedOptions.action, normalizedOptions)
            });
        },
        success: function(message, options) {
            var normalizedOptions = options && typeof options === 'object' ? options : {};
            normalizedOptions.type = 'success';
            return this.show(message, normalizedOptions);
        },
        error: function(message, options) {
            var normalizedOptions = options && typeof options === 'object' ? options : {};
            normalizedOptions.type = 'error';
            if (typeof normalizedOptions.duration !== 'number') {
                normalizedOptions.duration = 6000;
            }
            return this.show(message, normalizedOptions);
        },
        warning: function(message, options) {
            var normalizedOptions = options && typeof options === 'object' ? options : {};
            normalizedOptions.type = 'warning';
            return this.show(message, normalizedOptions);
        },
        info: function(message, options) {
            var normalizedOptions = options && typeof options === 'object' ? options : {};
            normalizedOptions.type = 'info';
            return this.show(message, normalizedOptions);
        },
        hideAll: function() {
            var toasts = document.querySelectorAll('.bbai-toast');
            for (var i = 0; i < toasts.length; i++) {
                hideToast(toasts[i]);
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureContainer);
    } else {
        ensureContainer();
    }
})(window, document);
