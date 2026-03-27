/**
 * Phase 17 — Floating assistant (REST-backed, no core UI refactor).
 */
(function () {
    'use strict';

    var cfg = window.BBAI_PHASE17 || {};
    var root = document.getElementById('bbai-phase17-assistant');
    if (!root || !cfg.restUrl || !cfg.nonce) {
        return;
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderReply(data) {
        var out = root.querySelector('[data-bbai-phase17-output]');
        if (!out) {
            return;
        }
        var html = '<p class="bbai-phase17-assistant__reply">' + esc(data.reply || '') + '</p>';
        if (data.sources && data.sources.length) {
            html += '<ul class="bbai-phase17-assistant__sources">';
            data.sources.forEach(function (s) {
                if (!s || !s.url) {
                    return;
                }
                html += '<li><a href="' + esc(s.url) + '">' + esc(s.label || s.url) + '</a></li>';
            });
            html += '</ul>';
        }
        out.innerHTML = html;
    }

    function sendMessage() {
        var ta = root.querySelector('[data-bbai-phase17-input]');
        var msg = ta ? String(ta.value || '').trim() : '';
        if (!msg) {
            return;
        }
        var page = '';
        try {
            page = new URLSearchParams(window.location.search).get('page') || '';
        } catch (e) {
            page = '';
        }
        var out = root.querySelector('[data-bbai-phase17-output]');
        if (out) {
            out.textContent = cfg.strings && cfg.strings.thinking ? cfg.strings.thinking : '…';
        }
        fetch(cfg.restUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': cfg.nonce
            },
            body: JSON.stringify({
                message: msg,
                context: { page: page }
            })
        })
            .then(function (r) {
                return r.json().then(function (body) {
                    if (!r.ok) {
                        throw new Error((body && body.message) || 'Request failed');
                    }
                    return body;
                });
            })
            .then(function (data) {
                renderReply(data);
            })
            .catch(function () {
                if (out) {
                    out.textContent =
                        cfg.strings && cfg.strings.error
                            ? cfg.strings.error
                            : 'Something went wrong. Try again.';
                }
            });
    }

    root.innerHTML =
        '<button type="button" class="bbai-phase17-assistant__fab" data-bbai-phase17-toggle aria-expanded="false" aria-controls="bbai-phase17-assistant-panel">' +
        esc(cfg.strings && cfg.strings.fab ? cfg.strings.fab : 'Help') +
        '</button>' +
        '<div id="bbai-phase17-assistant-panel" class="bbai-phase17-assistant__panel" role="dialog" aria-label="' +
        esc(cfg.strings && cfg.strings.title ? cfg.strings.title : 'Assistant') +
        '" hidden>' +
        '<div class="bbai-phase17-assistant__head">' +
        '<span>' +
        esc(cfg.strings && cfg.strings.title ? cfg.strings.title : 'BeepBeep guide') +
        '</span>' +
        '<button type="button" class="bbai-phase17-assistant__close" data-bbai-phase17-close aria-label="' +
        esc(cfg.strings && cfg.strings.close ? cfg.strings.close : 'Close') +
        '">×</button></div>' +
        '<div class="bbai-phase17-assistant__body" data-bbai-phase17-output></div>' +
        '<div class="bbai-phase17-assistant__foot">' +
        '<label class="screen-reader-text" for="bbai-phase17-assistant-input">' +
        esc(cfg.strings && cfg.strings.ask ? cfg.strings.ask : 'Your question') +
        '</label>' +
        '<textarea id="bbai-phase17-assistant-input" data-bbai-phase17-input rows="2" placeholder="' +
        esc(cfg.strings && cfg.strings.placeholder ? cfg.strings.placeholder : '') +
        '"></textarea>' +
        '<button type="button" class="button button-primary" data-bbai-phase17-send>' +
        esc(cfg.strings && cfg.strings.send ? cfg.strings.send : 'Ask') +
        '</button></div></div>';

    var panel = root.querySelector('.bbai-phase17-assistant__panel');
    var fab = root.querySelector('[data-bbai-phase17-toggle]');

    fab.addEventListener('click', function () {
        var willOpen = panel.hidden;
        panel.hidden = !willOpen;
        fab.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) {
            var ta = root.querySelector('[data-bbai-phase17-input]');
            if (ta) {
                ta.focus();
            }
        }
    });

    root.querySelector('[data-bbai-phase17-close]').addEventListener('click', function () {
        panel.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
    });

    root.querySelector('[data-bbai-phase17-send]').addEventListener('click', sendMessage);

    root.querySelector('[data-bbai-phase17-input]').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            sendMessage();
        }
    });

    /**
     * One-click improve (weak ALT rows) — REST + full page refresh for consistent library state.
     */
    window.bbaiHandlePhase17ImproveAlt = function (e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        if (e && typeof e.stopPropagation === 'function') {
            e.stopPropagation();
        }
        var btn = this;
        if (!btn || btn.getAttribute('aria-disabled') === 'true' || (btn.classList && btn.classList.contains('bbai-is-locked'))) {
            return false;
        }
        var idRaw = btn.getAttribute('data-attachment-id') || '';
        var id = parseInt(idRaw, 10);
        if (!id || id <= 0 || !cfg.improveUrlTemplate || !cfg.nonce) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('error', cfg.strings && cfg.strings.improveFail ? cfg.strings.improveFail : 'Could not improve ALT text.');
            }
            return false;
        }
        var baseLabel = (btn.textContent || '').trim();
        var working =
            cfg.strings && cfg.strings.improveWorking ? cfg.strings.improveWorking : 'Improving…';
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        if (baseLabel) {
            btn.textContent = working;
        }
        var url = cfg.improveUrlTemplate.replace(/\/?$/, '/') + id;
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': cfg.nonce
            }
        })
            .then(function (r) {
                return r.json().then(function (body) {
                    return { ok: r.ok, body: body };
                });
            })
            .then(function (res) {
                if (res.ok && res.body && res.body.alt) {
                    var msg =
                        cfg.strings && cfg.strings.improveDone
                            ? cfg.strings.improveDone
                            : 'ALT text updated.';
                    if (res.body.text_only_tip && window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                        var tipLabel = cfg.strings && cfg.strings.improveTip ? cfg.strings.improveTip : 'Tip';
                        window.bbaiPushToast('info', tipLabel + ': ' + String(res.body.text_only_tip));
                    }
                    if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                        window.bbaiPushToast('success', msg);
                    }
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 600);
                    return;
                }
                var err = (res.body && (res.body.message || res.body.code)) || '';
                var tip = res.body && res.body.data && res.body.data.text_only_tip ? res.body.data.text_only_tip : res.body.text_only_tip;
                if (tip && window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    var tl = cfg.strings && cfg.strings.improveTip ? cfg.strings.improveTip : 'Tip';
                    window.bbaiPushToast('info', tl + ': ' + String(tip));
                }
                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast(
                        'error',
                        err ||
                            (cfg.strings && cfg.strings.improveFail
                                ? cfg.strings.improveFail
                                : 'Could not improve ALT text.')
                    );
                }
            })
            .catch(function () {
                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast(
                        'error',
                        cfg.strings && cfg.strings.improveFail
                            ? cfg.strings.improveFail
                            : 'Could not improve ALT text.'
                    );
                }
            })
            .finally(function () {
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
                if (baseLabel) {
                    btn.textContent = baseLabel;
                }
            });
        return false;
    };
})();
