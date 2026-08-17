(function (global, document) {
    'use strict';

    var hideTimer = null;
    var hasSeenRunning = false;
    var i18n = global.wp && global.wp.i18n ? global.wp.i18n : {};
    var __ = typeof i18n.__ === 'function' ? i18n.__ : function (text) { return text; };
    var sprintf = typeof i18n.sprintf === 'function'
        ? i18n.sprintf
        : function (format, current, total) {
            return format.replace('%1$s', current).replace('%2$s', total);
        };

    function getNodes() {
        var root = document.querySelector('[data-bbai-daily-generation-progress="1"]');
        if (!root) {
            return null;
        }

        return {
            root: root,
            label: root.querySelector('[data-bbai-daily-generation-progress-label]'),
            percent: root.querySelector('[data-bbai-daily-generation-progress-percent]'),
            track: root.querySelector('[data-bbai-daily-generation-progress-track]'),
            fill: root.querySelector('[data-bbai-daily-generation-progress-fill]')
        };
    }

    function setHidden(nodes, hidden) {
        nodes.root.hidden = hidden;
        nodes.root.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    }

    function render(state) {
        var nodes = getNodes();
        var status = String(state && state.status || 'idle');
        var running = !!(state && state.running) || status === 'processing';
        var terminal = status === 'complete' || status === 'partial';
        var total = Math.max(0, parseInt(state && state.total, 10) || 0);
        var progress = Math.max(0, parseInt(state && state.progress, 10) || 0);
        var percentage = total > 0
            ? Math.min(100, Math.round((progress / total) * 100))
            : Math.max(0, Math.min(100, parseInt(state && state.percentage, 10) || 0));
        var activeImage = total > 0 ? Math.min(total, progress + 1) : 0;

        if (!nodes) {
            return;
        }

        if (hideTimer) {
            global.clearTimeout(hideTimer);
            hideTimer = null;
        }

        if (!running && !terminal) {
            setHidden(nodes, true);
            return;
        }

        if (running) {
            hasSeenRunning = true;
        }
        if (terminal && !hasSeenRunning) {
            setHidden(nodes, true);
            return;
        }

        setHidden(nodes, false);
        nodes.root.classList.toggle('is-complete', terminal);
        nodes.root.classList.toggle('is-indeterminate', running && percentage <= 0);

        if (terminal) {
            nodes.label.textContent = total > 0
                ? sprintf(__('%1$s of %2$s images complete', 'beepbeep-ai-alt-text-generator'), total, total)
                : __('Generation complete', 'beepbeep-ai-alt-text-generator');
            nodes.percent.textContent = '100%';
            nodes.fill.style.width = '100%';
            nodes.track.setAttribute('aria-valuenow', '100');
            hideTimer = global.setTimeout(function () {
                setHidden(nodes, true);
                hasSeenRunning = false;
            }, 1400);
            return;
        }

        nodes.label.textContent = total > 0
            ? sprintf(
                __('Generating image %1$s of %2$s…', 'beepbeep-ai-alt-text-generator'),
                Math.max(1, activeImage),
                total
            )
            : __('Preparing image generation…', 'beepbeep-ai-alt-text-generator');
        nodes.percent.textContent = percentage > 0
            ? percentage + '%'
            : __('Starting', 'beepbeep-ai-alt-text-generator');
        nodes.fill.style.width = percentage > 0 ? percentage + '%' : '12%';
        nodes.track.setAttribute('aria-valuenow', String(percentage));
    }

    function init() {
        if (!global.bbaiJobState || typeof global.bbaiJobState.subscribe !== 'function') {
            return;
        }

        global.bbaiJobState.subscribe(render);
        if (typeof global.bbaiJobState.getState === 'function') {
            render(global.bbaiJobState.getState());
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}(window, document));
