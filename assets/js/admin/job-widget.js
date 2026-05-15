/**
 * Floating Job Widget
 *
 * Persistent mini progress indicator visible while the modal is minimised.
 * Reads from window.bbaiJobState.
 *
 * @package BeepBeep_AI
 * @since 5.1.0
 */
(function ($) {
    'use strict';

    var WIDGET_ID = 'bbai-job-widget';
    var $widget = null;
    var created = false;

    function escapeHtml(text) {
        var fn = window.bbaiEscapeHtml || function (t) {
            var d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        };
        return fn(text);
    }

    function createWidget() {
        if (created) return;
        created = true;

        var html =
            '<div id="' + WIDGET_ID + '" class="bbai-job-widget" hidden>' +
            '  <div class="bbai-job-widget__header">' +
            '    <span class="bbai-job-widget__status">Processing\u2026</span>' +
            '    <button type="button" class="bbai-job-widget__close" aria-label="Dismiss">&times;</button>' +
            '  </div>' +
            '  <div class="bbai-job-widget__body">' +
            '    <div class="bbai-job-widget__counts">' +
            '      <span class="bbai-job-widget__progress-text">0 / 0</span>' +
            '      <span class="bbai-job-widget__eta"></span>' +
            '    </div>' +
            '    <div class="bbai-job-widget__bar">' +
            '      <div class="bbai-job-widget__bar-fill" style="width:0%"></div>' +
            '    </div>' +
            '  </div>' +
            '  <ul class="bbai-job-widget__log" aria-label="Recent images" aria-live="polite"></ul>' +
            '  <button type="button" class="bbai-job-widget__view">View</button>' +
            '</div>';

        $('body').append(html);
        $widget = $('#' + WIDGET_ID);

        // View → reopen modal or navigate to dashboard
        // bbai-background-job.js intercepts this click first (capture phase) to
        // handle cross-page navigation.  We only reach here when the modal is
        // present on the current page.
        $widget.on('click', '.bbai-job-widget__view', function () {
            var state = window.bbaiJobState ? window.bbaiJobState.getState() : null;

            // Complete state → navigate to library for review.
            if (state && state.status === 'complete') {
                var adminUrl = (window.bbai_ajax && window.bbai_ajax.admin_url) ||
                               (window.bbai_env  && window.bbai_env.admin_url)  || '';
                if (adminUrl) {
                    window.location.assign(adminUrl + '?page=bbai&tab=library');
                }
                return;
            }

            // Processing / error → reopen progress modal if present.
            if (window.bbaiJobState) {
                window.bbaiJobState.update({ modalVisible: true });
            }
            var $modal = $('#bbai-bulk-progress-modal');
            if ($modal.length) {
                $modal.addClass('active');
                $('body').css('overflow', 'hidden');
            }
        });

        // Close → dismiss widget (does NOT cancel job).
        // Persist the dismissed state via bbaiBackgroundJob.dismiss() so it
        // survives page navigation.  Fall back to local hide if the module
        // hasn't loaded yet (defensive).
        $widget.on('click', '.bbai-job-widget__close', function () {
            $widget.prop('hidden', true);
            if (window.bbaiBackgroundJob && typeof window.bbaiBackgroundJob.dismiss === 'function') {
                window.bbaiBackgroundJob.dismiss();
            }
        });
    }

    function isOnDashboardPage() {
        return !!document.querySelector('[data-bbai-logged-in-dashboard]');
    }

    var renderedLogLength = 0;

    function renderWidgetLog(entries) {
        if (!$widget) return;
        var $log = $widget.find('.bbai-job-widget__log');
        if (!$log.length) return;

        // Only re-render when new entries arrive
        if (entries.length === renderedLogLength) return;
        renderedLogLength = entries.length;

        $log.empty();
        // Show most-recent last (bottom = newest), capped at 3 visible rows
        var visible = entries.slice(-3);
        for (var i = 0; i < visible.length; i++) {
            var e = visible[i];
            var icon = e.success ? '✓' : '✕';
            var cls  = e.success ? 'bbai-job-widget__log-entry--ok' : 'bbai-job-widget__log-entry--err';
            var title = String(e.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            $log.append(
                '<li class="bbai-job-widget__log-entry ' + cls + '">' +
                '  <span class="bbai-job-widget__log-icon" aria-hidden="true">' + icon + '</span>' +
                '  <span class="bbai-job-widget__log-text">' + title + '</span>' +
                '</li>'
            );
        }
    }

    function render(state) {
        if (!$widget) return;

        // When a job completes and the user is already on the dashboard page, the
        // dashboard itself updates to show the result — the floating widget is
        // redundant and should stay hidden.
        if (state.status === 'complete' && isOnDashboardPage()) {
            $widget.prop('hidden', true);
            return;
        }

        // Show widget when: job running but modal hidden, OR job just completed/failed
        var shouldShow = !state.modalVisible && (
            state.status === 'processing' ||
            state.status === 'complete' ||
            state.status === 'error' ||
            state.status === 'quota'
        );

        // Hide when idle or modal is open during processing
        if (state.status === 'idle') {
            $widget.prop('hidden', true);
            renderedLogLength = 0;
            return;
        }

        if (state.modalVisible) {
            $widget.prop('hidden', true);
            return;
        }

        $widget.prop('hidden', !shouldShow);
        if (!shouldShow) return;

        var statusEl = $widget.find('.bbai-job-widget__status');
        var viewBtn  = $widget.find('.bbai-job-widget__view');

        if (state.status === 'complete') {
            var count = state.successes || state.progress || 0;
            statusEl.text(count + ' image' + (count !== 1 ? 's' : '') + ' optimised');
            $widget.find('.bbai-job-widget__progress-text').text('Generation complete');
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--error')
                   .addClass('bbai-job-widget--complete');
            viewBtn.text('Review images');
            // Force white text via inline style — WordPress admin button resets
            // can beat even !important class rules; inline style always wins.
            viewBtn[0].style.setProperty('color', '#ffffff', 'important');
            viewBtn[0].style.setProperty('background-color', '#16a34a', 'important');
        } else if (state.status === 'quota') {
            statusEl.text('Generation paused or failed.');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' processed' +
                (state.failures > 0 ? ', ' + state.failures + ' failed' : '')
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn[0].style.removeProperty('color');
            viewBtn[0].style.removeProperty('background-color');
            viewBtn.text('Resume status check');
        } else if (state.status === 'error') {
            statusEl.text('Generation paused or failed.');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' succeeded, ' + state.failures + ' failed'
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn[0].style.removeProperty('color');
            viewBtn[0].style.removeProperty('background-color');
            viewBtn.text('Resume status check');
        } else {
            var done  = state.progress || 0;
            var total = state.total    || 0;
            statusEl.text('Generating ALT text\u2026 ' + done + '/' + total + ' complete');
            $widget.find('.bbai-job-widget__progress-text').text(
                done + ' / ' + total
            );
            $widget.find('.bbai-job-widget__eta').text(state.eta ? 'ETA ' + state.eta : '');
            $widget.find('.bbai-job-widget__bar-fill').css('width', state.percentage + '%');
            $widget.removeClass('bbai-job-widget--complete bbai-job-widget--error')
                   .addClass('bbai-job-widget--processing');
            viewBtn[0].style.removeProperty('color');
            viewBtn[0].style.removeProperty('background-color');
            viewBtn.text('View progress');
        }

        // Render recent log entries
        renderWidgetLog(state.recentLog || []);
    }

    // Init when DOM ready
    $(function () {
        if (!window.bbaiJobState) return;

        createWidget();
        window.bbaiJobState.subscribe(render);

        // Render initial state
        render(window.bbaiJobState.getState());
    });

})(jQuery);
