/**
 * "More from Oppti" panel: reveal after a successful generation, dismiss for
 * good, and (only when the site opted in to usage analytics) send the
 * crosssell events through the existing beepbeepai_telemetry bridge.
 *
 * @package BeepBeep_AI
 */
(function (window, document) {
    'use strict';

    var cfg = window.BBAI_MORE_FROM_OPPTI || {};
    var telemetry = cfg.telemetry || {};
    var shownSent = false;

    function consented() {
        return telemetry.enabled === true && !!cfg.ajaxUrl && !!telemetry.nonce;
    }

    function post(body, preferFetch) {
        var data = new window.FormData();
        Object.keys(body).forEach(function (key) {
            data.append(key, body[key]);
        });
        if (preferFetch && typeof window.fetch === 'function') {
            window.fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(function () {});
            return;
        }
        if (window.navigator && typeof window.navigator.sendBeacon === 'function') {
            if (window.navigator.sendBeacon(cfg.ajaxUrl, data)) {
                return;
            }
        }
        if (typeof window.fetch === 'function') {
            window.fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(function () {});
        }
    }

    function send(panel, eventName, extra) {
        if (!consented()) {
            return;
        }
        var props = {
            from_plugin: panel.getAttribute('data-from') || '',
            account_state: panel.getAttribute('data-account-state') || '',
            placement: 'after_generation'
        };
        Object.keys(extra || {}).forEach(function (key) {
            props[key] = extra[key];
        });
        post({
            action: telemetry.action || 'beepbeepai_telemetry',
            nonce: telemetry.nonce,
            events: JSON.stringify([{ event: eventName, properties: props }])
        });
    }

    function suggested(panel) {
        var links = panel.querySelectorAll('[data-bbai-mfo-link]');
        var ids = [];
        for (var i = 0; i < links.length; i++) {
            ids.push(links[i].getAttribute('data-to'));
        }
        return ids.join('-');
    }

    function markShown(panel) {
        if (shownSent || panel.hidden) {
            return;
        }
        shownSent = true;
        send(panel, 'crosssell_panel_shown', { suggested: suggested(panel) });
    }

    function reveal(panel) {
        panel.hidden = false;
        panel.removeAttribute('data-await-success');
        markShown(panel);
    }

    function dismiss(panel) {
        panel.hidden = true;
        send(panel, 'crosssell_panel_dismissed', {});
        post({ action: cfg.dismissAction, nonce: cfg.dismissNonce }, true);
    }

    function bind(panel) {
        var form = panel.querySelector('[data-bbai-mfo-dismiss-form]');
        if (form) {
            form.addEventListener('submit', function (event) {
                // Without the ajax config the normal admin-post form submit still dismisses.
                if (!cfg.ajaxUrl || !cfg.dismissNonce || !cfg.dismissAction) {
                    return;
                }
                event.preventDefault();
                dismiss(panel);
            });
        }
        panel.addEventListener('click', function (event) {
            var target = event.target && event.target.closest ? event.target.closest('a') : null;
            if (!target || !panel.contains(target)) {
                return;
            }
            if (target.hasAttribute('data-bbai-mfo-link')) {
                send(panel, 'crosssell_clicked', {
                    to_plugin: target.getAttribute('data-to') || '',
                    link_target: target.getAttribute('data-link-target') || ''
                });
            }
        });

        if (panel.hasAttribute('data-await-success')) {
            document.addEventListener('bbai:generation:finished', function (event) {
                var detail = (event && event.detail) || {};
                if (Number(detail.successes || 0) > 0) {
                    reveal(panel);
                }
            });
            return;
        }
        markShown(panel);
    }

    function init() {
        var panel = document.querySelector('[data-bbai-mfo]');
        if (panel) {
            bind(panel);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
