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

    function runAction(button) {
        var actionKey = button.getAttribute('data-bbai-action');
        if (!actionKey || !config.actions || !config.actions[actionKey]) {
            return;
        }

        addNotice('info', (config.strings && config.strings.working) ? config.strings.working : __('Request submitted. This may take a moment.', 'beepbeep-ai-alt-text-generator'));
        setLoading(button, true);

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
