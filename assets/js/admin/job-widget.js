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

        // Close → dismiss widget (does NOT cancel job)
        $widget.on('click', '.bbai-job-widget__close', function () {
            $widget.prop('hidden', true);
        });
    }

    function render(state) {
        if (!$widget) return;

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
            statusEl.text(count + ' image' + (count !== 1 ? 's' : '') + ' optimised. Review suggestions.');
            $widget.find('.bbai-job-widget__progress-text').text('Generation complete');
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--error')
                   .addClass('bbai-job-widget--complete');
            viewBtn.text('Review images');
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
            viewBtn.text('View progress');
        }
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
