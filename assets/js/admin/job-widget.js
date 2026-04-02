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

        // View → reopen modal
        $widget.on('click', '.bbai-job-widget__view', function () {
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

        // Show widget when: job running but modal hidden, OR job just completed
        var shouldShow = !state.modalVisible && (
            state.status === 'processing' ||
            state.status === 'complete' ||
            state.status === 'error' ||
            state.status === 'quota'
        );

        // Hide when idle or modal is visible during processing
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

        // Status text
        var statusEl = $widget.find('.bbai-job-widget__status');
        var viewBtn = $widget.find('.bbai-job-widget__view');

        if (state.status === 'complete') {
            statusEl.text('ALT text generation complete');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' image' + (state.successes !== 1 ? 's' : '') + ' updated'
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--error')
                   .addClass('bbai-job-widget--complete');
            viewBtn.text('Review');
        } else if (state.status === 'quota') {
            statusEl.text('Credits exhausted');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' processed, ' + state.skipped + ' skipped' +
                (state.failures > 0 ? ', ' + state.failures + ' failed' : '')
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn.text('View');
        } else if (state.status === 'error') {
            statusEl.text('Completed with issues');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.successes + ' succeeded, ' + state.failures + ' failed'
            );
            $widget.find('.bbai-job-widget__eta').text('');
            $widget.find('.bbai-job-widget__bar-fill').css('width', '100%');
            $widget.removeClass('bbai-job-widget--processing bbai-job-widget--complete')
                   .addClass('bbai-job-widget--error');
            viewBtn.text('View');
        } else {
            statusEl.text('Generating ALT text\u2026');
            $widget.find('.bbai-job-widget__progress-text').text(
                state.progress + ' / ' + state.total
            );
            $widget.find('.bbai-job-widget__eta').text(state.eta ? 'ETA ' + state.eta : '');
            $widget.find('.bbai-job-widget__bar-fill').css('width', state.percentage + '%');
            $widget.removeClass('bbai-job-widget--complete bbai-job-widget--error')
                   .addClass('bbai-job-widget--processing');
            viewBtn.text('View');
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
