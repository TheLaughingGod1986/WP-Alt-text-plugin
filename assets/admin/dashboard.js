(function () {
    'use strict';

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function (text) { return text; };

    var dashboard = document.querySelector('.bbai-dashboard');
    if (!dashboard || !window.BBAI_DASHBOARD) {
        return;
    }

    var config = window.BBAI_DASHBOARD;
    var notices = document.getElementById('bbai-dashboard-notices');
    var usage = config.usage || {};
    var remaining = parseInt(usage.remaining, 10);
    var used = parseInt(usage.used, 10);
    var limit = parseInt(usage.limit, 10);
    var outOfCredits = false;
    var outOfCreditsMessage = __('You are out of credits for this month. Upgrade to continue now, or wait until your monthly reset date.', 'beepbeep-ai-alt-text-generator');

    function parseUsageFromDom() {
        var selectors = [
            '.bbai-card__subtitle',
            '.bbai-usage-count',
            '.bbai-usage-count-text'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (!el || !el.textContent) {
                continue;
            }

            var match = el.textContent.match(/([0-9][0-9,]*)\s*of\s*([0-9][0-9,]*)/i);
            if (!match) {
                continue;
            }

            var domUsed = parseInt(String(match[1]).replace(/,/g, ''), 10);
            var domLimit = parseInt(String(match[2]).replace(/,/g, ''), 10);
            if (!isNaN(domUsed) && !isNaN(domLimit) && domLimit > 0) {
                return { used: domUsed, limit: domLimit };
            }
        }

        return null;
    }

    function isOutOfCredits() {
        if (!isNaN(remaining) && remaining <= 0) {
            return true;
        }

        if (!isNaN(used) && !isNaN(limit) && limit > 0 && used >= limit) {
            return true;
        }

        var domUsage = parseUsageFromDom();
        if (domUsage && domUsage.limit > 0 && domUsage.used >= domUsage.limit) {
            return true;
        }

        return false;
    }

    function addNotice(type, message) {
        if (!notices) {
            return;
        }

        var notice = document.createElement('div');
        notice.className = 'bbai-dashboard-notice bbai-dashboard-notice--' + type;

        var text = document.createElement('div');
        text.textContent = message;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'bbai-dashboard-notice__close';
        close.setAttribute('aria-label', (config.strings && config.strings.close) ? config.strings.close : __('Close', 'beepbeep-ai-alt-text-generator'));
        close.textContent = 'x';
        close.addEventListener('click', function () {
            notice.remove();
        });

        notice.appendChild(text);
        notice.appendChild(close);
        notices.appendChild(notice);
    }

    function animateProgressRings() {
        var rings = document.querySelectorAll('.bbai-progress-ring__value');
        rings.forEach(function (circle) {
            var percent = parseFloat(circle.getAttribute('data-progress')) || 0;
            var circumference = parseFloat(circle.getAttribute('data-circumference')) || 0;
            var offset = circumference * (1 - Math.min(100, Math.max(0, percent)) / 100);
            requestAnimationFrame(function () {
                circle.style.strokeDashoffset = offset;
            });
        });
    }

    function disableGenerationButtons() {
        outOfCredits = isOutOfCredits();
        if (!outOfCredits) {
            return;
        }

        dashboard.querySelectorAll('[data-bbai-action], #bbai-batch-regenerate').forEach(function (button) {
            if (!button) {
                return;
            }
            button.setAttribute('disabled', 'disabled');
            button.setAttribute('title', outOfCreditsMessage);
            button.style.pointerEvents = 'none';
            button.style.cursor = 'not-allowed';
        });
    }

    function setLoading(button, isLoading) {
        if (!button) {
            return;
        }

        var text = button.querySelector('.bbai-dashboard-btn__text');
        if (isLoading) {
            button.classList.add('is-loading');
            button.setAttribute('disabled', 'disabled');
            if (text && !button.dataset.originalText) {
                button.dataset.originalText = text.textContent;
                text.textContent = (config.strings && config.strings.working) ? config.strings.working : __('Working...', 'beepbeep-ai-alt-text-generator');
            }
        } else {
            button.classList.remove('is-loading');
            button.removeAttribute('disabled');
            if (text && button.dataset.originalText) {
                text.textContent = button.dataset.originalText;
                delete button.dataset.originalText;
            }
        }
    }

    function parseJsonResponse(response) {
        return response.text().then(function (text) {
            if (!text) {
                return {};
            }

            try {
                return JSON.parse(text);
            } catch (error) {
                return {};
            }
        });
    }

    function getErrorMessage(data, fallback) {
        if (data && data.data && data.data.message) {
            return data.data.message;
        }
        if (data && data.message) {
            return data.message;
        }
        return fallback;
    }

    function fetchActionIds(actionKey) {
        var listUrl = actionKey === 'generate_missing' ? config.restListMissing : config.restListAll;
        if (!listUrl || !config.restNonce) {
            return Promise.reject(new Error((config.strings && config.strings.error) ? config.strings.error : __('Error.', 'beepbeep-ai-alt-text-generator')));
        }

        return fetch(listUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': config.restNonce
            }
        })
            .then(function (response) {
                return parseJsonResponse(response).then(function (data) {
                    if (!response.ok) {
                        throw new Error(getErrorMessage(data, (config.strings && config.strings.error) ? config.strings.error : __('Error.', 'beepbeep-ai-alt-text-generator')));
                    }
                    if (!data || !Array.isArray(data.ids)) {
                        return [];
                    }
                    return data.ids;
                });
            });
    }

    function queueBulkIds(ids, source) {
        if (!config.ajaxUrl || !config.bulkNonce || !config.bulkQueueAction) {
            return Promise.reject(new Error((config.strings && config.strings.error) ? config.strings.error : __('Error.', 'beepbeep-ai-alt-text-generator')));
        }

        var formData = new FormData();
        formData.append('action', config.bulkQueueAction);
        formData.append('nonce', config.bulkNonce);
        formData.append('source', source);
        formData.append('skip_schedule', '0');

        ids.forEach(function (id) {
            formData.append('attachment_ids[]', String(id));
        });

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return parseJsonResponse(response).then(function (data) {
                if (!response.ok || !data || data.success !== true) {
                    throw new Error(getErrorMessage(data, (config.strings && config.strings.error) ? config.strings.error : __('Error.', 'beepbeep-ai-alt-text-generator')));
                }

                return data;
            });
        });
    }

    function runBulkAction(actionKey) {
        var source = actionKey === 'reoptimize_all' ? 'bulk-regenerate' : 'bulk';

        return fetchActionIds(actionKey).then(function (ids) {
            if (!ids.length) {
                return {
                    empty: true,
                    message: (config.strings && config.strings.nothingToProcess)
                        ? config.strings.nothingToProcess
                        : __('No images found for this action.', 'beepbeep-ai-alt-text-generator')
                };
            }

            return queueBulkIds(ids, source);
        });
    }

    function runAction(button) {
        outOfCredits = isOutOfCredits();
        if (outOfCredits) {
            button.setAttribute('disabled', 'disabled');
            button.style.pointerEvents = 'none';
            button.style.cursor = 'not-allowed';
            // Message already shown by PHP in dashboard-body.php
            return;
        }

        var actionKey = button.getAttribute('data-bbai-action');
        if (!actionKey || !config.actions || !config.actions[actionKey]) {
            return;
        }

        if (actionKey === 'reoptimize_all') {
            var confirmMessage = (config.strings && config.strings.confirmReoptimize)
                ? config.strings.confirmReoptimize
                : __('This will regenerate alt text for ALL images, replacing existing alt text. Are you sure?', 'beepbeep-ai-alt-text-generator');
            if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                window.bbaiModal.show({
                    type: 'warning',
                    title: __('Re-optimise All Images', 'beepbeep-ai-alt-text-generator'),
                    message: confirmMessage,
                    buttons: [
                        { text: __('Yes, re-optimise all', 'beepbeep-ai-alt-text-generator'), primary: true, action: function() { window.bbaiModal.close(); proceedAction(button, actionKey); } },
                        { text: __('Cancel', 'beepbeep-ai-alt-text-generator'), primary: false, action: function() { window.bbaiModal.close(); } }
                    ]
                });
                return;
            }
            if (!window.confirm(confirmMessage)) {
                return;
            }
        }

        proceedAction(button, actionKey);
    }

    function proceedAction(button, actionKey) {
        addNotice('info', (config.strings && config.strings.working) ? config.strings.working : __('Request submitted. This may take a moment.', 'beepbeep-ai-alt-text-generator'));
        setLoading(button, true);

        if (actionKey === 'generate_missing' || actionKey === 'reoptimize_all') {
            runBulkAction(actionKey)
                .then(function (data) {
                    if (data && data.empty) {
                        addNotice('info', data.message);
                        return;
                    }

                    var successMessage = data && data.data && data.data.message ? data.data.message : (config.strings && config.strings.success);
                    addNotice('success', successMessage || __('Success.', 'beepbeep-ai-alt-text-generator'));
                })
                .catch(function (error) {
                    addNotice('error', error && error.message ? error.message : ((config.strings && config.strings.error) ? config.strings.error : __('Error.', 'beepbeep-ai-alt-text-generator')));
                })
                .finally(function () {
                    setLoading(button, false);
                });
            return;
        }

        var formData = new FormData();
        formData.append('action', config.actions[actionKey]);
        formData.append('nonce', config.nonce || '');

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    var successMessage = data.data && data.data.message ? data.data.message : (config.strings && config.strings.success);
                    addNotice('success', successMessage || __('Success.', 'beepbeep-ai-alt-text-generator'));
                } else {
                    var errorMessage = data && data.data && data.data.message ? data.data.message : (config.strings && config.strings.error);
                    addNotice('error', errorMessage || __('Error.', 'beepbeep-ai-alt-text-generator'));
                }
            })
            .catch(function () {
                addNotice('error', (config.strings && config.strings.error) ? config.strings.error : __('Error.', 'beepbeep-ai-alt-text-generator'));
            })
            .finally(function () {
                setLoading(button, false);
            });
    }

    dashboard.querySelectorAll('[data-bbai-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            runAction(button);
        });
    });

    disableGenerationButtons();

    function initModal() {
        var modal = document.getElementById('bbai-dashboard-modal');
        if (!modal) {
            return;
        }

        var openButtons = document.querySelectorAll('[data-bbai-modal-open]');
        var closeButtons = modal.querySelectorAll('[data-bbai-modal-close]');
        var lastFocused = null;

        function getFocusable(container) {
            return Array.prototype.slice.call(
                container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
            ).filter(function (el) {
                return !el.hasAttribute('disabled');
            });
        }

        function trapFocus(e) {
            if (e.key !== 'Tab') {
                return;
            }

            var focusable = getFocusable(modal);
            if (!focusable.length) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
                return;
            }

            if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        function openModal(trigger) {
            if (modal.classList.contains('is-open')) {
                return;
            }

            lastFocused = trigger || document.activeElement;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            var focusable = getFocusable(modal);
            if (focusable.length) {
                focusable[0].focus();
            }
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(button);
            });
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                closeModal();
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target && event.target.classList.contains('bbai-modal__overlay')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (!modal.classList.contains('is-open')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
                return;
            }

            trapFocus(event);
        });
    }

    animateProgressRings();
    initModal();
})();
